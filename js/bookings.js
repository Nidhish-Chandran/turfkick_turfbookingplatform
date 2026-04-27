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

        // Get filter and sort values
        const sportFilter = this.currentFilter || 'all';
        const sortType = document.getElementById('sortSelect')?.value || 'none';

        let filtered = this.turfs.filter(t => {
            const matchesSearch = t.name.toLowerCase().includes(query) || t.location.toLowerCase().includes(query);
            const matchesSport = sportFilter === 'all' || t.sport_category === sportFilter;
            return matchesSearch && matchesSport;
        });

        // Sorting logic
        if (sortType === 'price_low') {
            filtered.sort((a, b) => a.price_per_hour - b.price_per_hour);
        } else if (sortType === 'price_high') {
            filtered.sort((a, b) => b.price_per_hour - a.price_per_hour);
        }

        container.innerHTML = filtered.map(t => `
            <div class="card" onclick="TurfKickBookings.openTurf(${t.id})">
                <div class="card-img">
                    <img src="${t.image_path || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80'}" alt="${t.name}">
                </div>
                <div class="card-body">
                    <h3 style="margin:0;">${t.name}</h3>
                    <p style="opacity:0.6; font-size:13px; margin:5px 0;">📍 ${t.location}</p>
                    <div style="color:var(--accent);">⭐ 4.5</div>
                    <div class="card-price">₹${t.price_per_hour} <small>/ hr</small></div>
                </div>
            </div>
        `).join('');
    },

    setFilter(sport, btn) {
        this.currentFilter = sport;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this.renderTurfs();
    },

    async openTurf(id) {
        const turf = this.turfs.find(t => t.id == id);
        if (!turf) return;

        const imgSrc = turf.image_path || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80';
        document.getElementById('modalImg').style.backgroundImage = `url(${imgSrc})`;
        document.getElementById('modalName').innerText = turf.name;
        document.getElementById('modalLoc').innerText = turf.location;
        document.getElementById('modalRating').innerText = `⭐ 4.5`;

        // Fetch slots for this turf
        await this.renderSlots(id);
        
        // Fetch equipment for this turf
        await this.renderEquipment(id);

        // Fetch reviews for this turf
        await this.renderReviews(id);

        document.getElementById('bookingContext').style.display = 'block';
        document.getElementById('statusContext').style.display = 'none';
        
        const m = document.getElementById('turfModal');
        m.style.display = 'flex';
        setTimeout(() => m.classList.add('active'), 10);
    },

    async renderReviews(turfId) {
        try {
            const response = await fetch(`api/get_reviews.php?turf_id=${turfId}`);
            const result = await response.json();
            
            let container = document.getElementById('modalReviews');
            if (!container) {
                const right = document.querySelector('.modal-right');
                const div = document.createElement('div');
                div.id = 'modalReviews';
                div.style.marginTop = '25px';
                div.style.borderTop = '1px solid var(--border)';
                div.style.paddingTop = '15px';
                right.appendChild(div);
                container = div;
            }

            if (result.status === 'success' && result.data.length > 0) {
                container.innerHTML = '<h4>Reviews</h4>' + result.data.map(r => `
                    <div style="background:rgba(255,255,255,0.05); padding:12px; border-radius:12px; margin-bottom:10px; border:1px solid var(--border);">
                        <div style="display:flex; justify-content:space-between;">
                            <strong>${r.user_name}</strong>
                            <span style="color:var(--accent);">${'★'.repeat(r.rating)}</span>
                        </div>
                        <p style="font-size:13px; margin:5px 0; opacity:0.8;">${r.comment}</p>
                        ${r.owner_reply ? `
                            <div style="margin-top:8px; padding:8px; background:rgba(153, 242, 200, 0.1); border-left:3px solid var(--secondary); border-radius:4px; font-size:12px;">
                                <strong style="color:var(--secondary);">Owner:</strong> ${r.owner_reply}
                            </div>
                        ` : ''}
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<h4>Reviews</h4><p style="opacity:0.5; font-size:12px;">No reviews yet.</p>';
            }
        } catch (error) {
            console.error('Error fetching reviews:', error);
        }
    },

    setStar(n) {
        this.currentRating = n;
        document.querySelectorAll('.star').forEach((s, idx) => {
            s.classList.toggle('active', idx < n);
        });
    },

    async submitReview() {
        if (!this.currentRating) return alert("Please select a rating!");
        const comment = document.querySelector('#ratingArea textarea').value;
        const bookingId = this.currentActiveBookingId;

        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('rating', this.currentRating);
        formData.append('comment', comment);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/submit_review.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.status === 'success') {
                this.closeModal('turfModal');
                this.fetchBookings();
            }
        } catch (error) {
            console.error('Error submitting review:', error);
        }
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

    async renderEquipment(turfId) {
        try {
            const response = await fetch(`api/get_equipment.php?turf_id=${turfId}`);
            const result = await response.json();
            const container = document.getElementById('accStore');
            
            if (result.status === 'success' && result.data.length > 0) {
                container.innerHTML = result.data.map(item => `
                    <div class="acc-item">
                        <span>⚽ ${item.name}</span>
                        <label><input type="checkbox" value="${item.id}"> +₹${item.price_per_session}</label>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p style="opacity:0.5; font-size:12px;">No additional equipment available for this turf.</p>';
            }
        } catch (error) {
            console.error('Error fetching equipment:', error);
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

        // Collect selected equipment
        const selectedEquipment = [];
        document.querySelectorAll('#accStore input[type="checkbox"]:checked').forEach(cb => {
            selectedEquipment.push(cb.value);
        });

        const formData = new FormData();
        formData.append('turf_id', this.selectedTurfId);
        formData.append('slot_id', this.selectedSlotId);
        formData.append('date', date);
        formData.append('price', turf.price_per_hour);
        formData.append('equipment_ids', JSON.stringify(selectedEquipment));
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
        if (!m) return;
        m.classList.remove('active');
        setTimeout(() => m.style.display = 'none', 300);
    },

    openModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        
        if (id === 'historyModal') {
            this.renderLogs();
        }
        
        m.style.display = 'flex';
        setTimeout(() => m.classList.add('active'), 10);
    },

    renderLogs() {
        const list = document.getElementById('historyList');
        if (!list) return;
        
        if (this.bookings.length === 0) {
            list.innerHTML = '<p style="text-align:center; opacity:0.5; padding:20px;">No bookings found.</p>';
            return;
        }

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
        this.currentActiveBookingId = id;

        document.getElementById('modalImg').style.backgroundImage = `url(${b.image_path || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80'})`;
        document.getElementById('modalName').innerText = b.turf_name;
        document.getElementById('modalLoc').innerText = b.location;
        document.getElementById('modalRating').innerText = `⭐ 4.5`;

        document.getElementById('bookingContext').style.display = 'none';
        document.getElementById('statusContext').style.display = 'block';
        
        document.getElementById('statusArea').innerHTML = `
            <span class="tag tag-${b.status}">${b.status}</span>
            <div style="font-weight: 700; margin-top: 10px;">📅 ${b.booking_date} at ${b.slot_label}</div>
            <button class="btn btn-outline" style="margin-top:10px; font-size:12px;" onclick="TurfKickBookings.openComplaintForm(${b.turf_id}, ${b.id})">Report Issue / Complaint</button>
        `;

        const cancelBtn = document.getElementById('cancelBtn');
        const ratingArea = document.getElementById('ratingArea');

        if (b.status === 'upcoming') {
            cancelBtn.style.display = 'block';
            cancelBtn.onclick = () => this.cancelBooking(b.id);
            ratingArea.style.display = 'none';
        } else if (b.status === 'completed') {
            cancelBtn.style.display = 'none';
            ratingArea.style.display = 'block';
        } else {
            cancelBtn.style.display = 'none';
            ratingArea.style.display = 'none';
        }

        // Hide reviews when viewing booking status
        const revContainer = document.getElementById('modalReviews');
        if (revContainer) revContainer.innerHTML = '';

        this.closeModal('historyModal');
        const m = document.getElementById('turfModal');
        m.style.display = 'flex';
        setTimeout(() => m.classList.add('active'), 10);
    },

    openComplaintForm(turfId, bookingId) {
        this.selectedTurfId = turfId;
        this.selectedBookingId = bookingId;
        
        document.getElementById('statusArea').innerHTML = `
            <h3>Raise Complaint</h3>
            <input type="text" id="compSubject" placeholder="Subject (e.g., Booking issue)" style="width:100%; padding:10px; margin-bottom:10px; background:rgba(0,0,0,0.2); border:1px solid var(--border); color:#fff; border-radius:8px;">
            <textarea id="compDesc" placeholder="Describe the issue..." style="width:100%; height:80px; padding:10px; background:rgba(0,0,0,0.2); border:1px solid var(--border); color:#fff; border-radius:8px;"></textarea>
            <button class="btn btn-main" onclick="TurfKickBookings.submitComplaint()">Submit Complaint</button>
            <button class="btn btn-outline" onclick="TurfKickBookings.openBooking(${bookingId})">Back</button>
        `;
    },

    async submitComplaint() {
        const subject = document.getElementById('compSubject').value;
        const description = document.getElementById('compDesc').value;

        if(!subject || !description) return alert("Please fill all fields");

        const formData = new FormData();
        formData.append('turf_id', this.selectedTurfId);
        formData.append('booking_id', this.selectedBookingId);
        formData.append('subject', subject);
        formData.append('description', description);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/create_complaint.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.status === 'success') {
                this.openBooking(this.selectedBookingId);
            }
        } catch (error) {
            console.error('Error submitting complaint:', error);
        }
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

// Expose to global scope for HTML onclick events
window.bookNow = () => TurfKickBookings.bookNow();
window.closeModal = (id) => TurfKickBookings.closeModal(id);
window.openModal = (id) => TurfKickBookings.openModal(id);
window.renderLogs = () => TurfKickBookings.renderLogs();
window.setStar = (n) => TurfKickBookings.setStar(n);
window.submitReview = () => TurfKickBookings.submitReview();
window.submitComplaint = () => TurfKickBookings.submitComplaint();
