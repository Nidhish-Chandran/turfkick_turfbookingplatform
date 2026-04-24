/**
 * TurfKick Bookings & Dashboard JS
 */

const TurfKickBookings = {
    turfs: [],
    bookings: [],
    csrfToken: null,

    async init() {
        await this.fetchToken();
        await this.fetchUser();
        await this.fetchTurfs();
        await this.fetchBookings();
    },

    async fetchToken() {
        try {
            const response = await fetch('api/get_token.php');
            const result = await response.json();
            if (result.status === 'success') {
                this.csrfToken = result.data.csrf_token;
            }
        } catch (error) {
            console.error('Error fetching token:', error);
        }
    },

    async fetchUser() {
        try {
            const response = await fetch('api/get_user.php');
            const result = await response.json();
            if (result.status === 'success') {
                document.querySelector('h1').innerText = `Welcome Back, ${result.data.user_name}!`;
                document.querySelector('.user-profile').innerText = result.data.user_name.split(' ').map(n => n[0]).join('').toUpperCase();
            } else {
                window.location.href = 'index.html';
            }
        } catch (error) {
            console.error('Error fetching user:', error);
        }
    },

    async fetchTurfs() {
        try {
            const response = await fetch('api/get_turfs.php');
            const result = await response.json();
            if (result.status === 'success') {
                this.turfs = result.data;
                this.renderTurfs();
            }
        } catch (error) {
            console.error('Error fetching turfs:', error);
        }
    },

    async fetchBookings() {
        try {
            const response = await fetch('api/get_bookings.php');
            const result = await response.json();
            if (result.status === 'success') {
                this.bookings = result.data;
            }
        } catch (error) {
            console.error('Error fetching bookings:', error);
        }
    },

    renderTurfs() {
        const query = document.getElementById('search').value.toLowerCase();
        const container = document.getElementById('listings');
        if (!container) return;

        const filtered = this.turfs.filter(t => {
            const matchesSearch = t.name.toLowerCase().includes(query) || t.location.toLowerCase().includes(query);
            // Add filter logic here if needed
            return matchesSearch;
        });

        container.innerHTML = filtered.map(t => `
            <div class="card" onclick="TurfKickBookings.openTurf(${t.id})">
                <div class="card-img"><img src="${t.image_path || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80'}"></div>
                <div class="card-body">
                    <h3 style="margin:0;">${t.name}</h3>
                    <p style="opacity:0.6; font-size:13px; margin:5px 0;">📍 ${t.location}</p>
                    <div style="color:var(--accent);">⭐ 4.5</div>
                    <div class="card-price">₹${t.price_per_hour} <small>/ hr</small></div>
                </div>
            </div>
        `).join('');
    },

    async openTurf(id) {
        const turf = this.turfs.find(t => t.id == id);
        if (!turf) return;

        document.getElementById('modalImg').style.backgroundImage = `url(${turf.image_path || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80'})`;
        document.getElementById('modalName').innerText = turf.name;
        document.getElementById('modalLoc').innerText = turf.location;
        document.getElementById('modalRating').innerText = `⭐ 4.5`;

        // Fetch slots for this turf
        await this.renderSlots(id);

        document.getElementById('bookingContext').style.display = 'block';
        document.getElementById('statusContext').style.display = 'none';
        
        const m = document.getElementById('turfModal');
        m.style.display = 'flex';
        setTimeout(() => m.classList.add('active'), 10);
    },

    async renderSlots(turfId) {
        try {
            const response = await fetch(`api/get_slots.php?turf_id=${turfId}`);
            const result = await response.json();
            const container = document.getElementById('modalSlots');
            
            if (result.status === 'success') {
                const slots = result.data;
                container.innerHTML = slots.map(s => `
                    <div class="slot" data-slot-id="${s.id}" onclick="TurfKickBookings.selectSlot(this, ${turfId}, ${s.id})">
                        ${s.slot_label}
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p>No slots available</p>';
            }
        } catch (error) {
            console.error('Error fetching slots:', error);
        }
    },

    selectSlot(el, turfId, slotId) {
        document.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
        el.classList.add('selected');
        this.selectedTurfId = turfId;
        this.selectedSlotId = slotId;
        
        // Real-time availability check
        this.checkAvailability(turfId, slotId);
    },

    async checkAvailability(turfId, slotId) {
        const date = new Date().toISOString().split('T')[0]; // For demo, use today
        try {
            const response = await fetch(`api/check_availability.php?turf_id=${turfId}&slot_id=${slotId}&date=${date}`);
            const result = await response.json();
            if (result.status === 'success' && !result.data.available) {
                const el = document.querySelector(`.slot[data-slot-id="${slotId}"]`);
                el.classList.add('booked');
                el.classList.remove('selected');
                alert("This slot is already booked!");
            }
        } catch (error) {
            console.error('Error checking availability:', error);
        }
    },

    async bookNow() {
        if (!this.selectedSlotId) return alert("Select a time slot!");

        const turf = this.turfs.find(t => t.id == this.selectedTurfId);
        const date = new Date().toISOString().split('T')[0];

        const formData = new FormData();
        formData.append('turf_id', this.selectedTurfId);
        formData.append('slot_id', this.selectedSlotId);
        formData.append('date', date);
        formData.append('price', turf.price_per_hour);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/create_booking.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.status === 'success') {
                alert(result.message);
                this.closeModal('turfModal');
                this.fetchBookings();
            } else {
                alert('Booking failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error booking:', error);
        }
    },

    closeModal(id) {
        const m = document.getElementById(id);
        m.classList.remove('active');
        setTimeout(() => m.style.display = 'none', 300);
    },

    renderLogs() {
        const list = document.getElementById('historyList');
        list.innerHTML = this.bookings.map(b => `
            <div class="log-item" onclick="TurfKickBookings.openBooking(${b.id})">
                <div>
                    <strong style="display:block;">${b.turf_name}</strong>
                    <small style="opacity:0.6;">${b.booking_date} • ${b.slot_label}</small>
                </div>
                <span class="tag tag-${b.status}">${b.status}</span>
            </div>
        `).join('');
    },

    async openBooking(id) {
        const b = this.bookings.find(booking => booking.id == id);
        if (!b) return;

        document.getElementById('modalImg').style.backgroundImage = `url(${b.image_path || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80'})`;
        document.getElementById('modalName').innerText = b.turf_name;
        document.getElementById('modalLoc').innerText = b.location;
        document.getElementById('modalRating').innerText = `⭐ 4.5`;

        document.getElementById('bookingContext').style.display = 'none';
        document.getElementById('statusContext').style.display = 'block';
        
        document.getElementById('statusArea').innerHTML = `
            <span class="tag tag-${b.status}">${b.status}</span>
            <div style="font-weight: 700; margin-top: 10px;">📅 ${b.booking_date} at ${b.slot_label}</div>
        `;

        const cancelBtn = document.getElementById('cancelBtn');
        if (b.status === 'upcoming') {
            cancelBtn.style.display = 'block';
            cancelBtn.onclick = () => this.cancelBooking(b.id);
        } else {
            cancelBtn.style.display = 'none';
        }

        this.closeModal('historyModal');
        const m = document.getElementById('turfModal');
        m.style.display = 'flex';
        setTimeout(() => m.classList.add('active'), 10);
    },

    async cancelBooking(bookingId) {
        if (!confirm("Cancel this booking?")) return;

        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/cancel_booking.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.status === 'success') {
                alert(result.message);
                this.closeModal('turfModal');
                this.fetchBookings();
            } else {
                alert('Cancel failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error cancelling:', error);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    TurfKickBookings.init();
});
