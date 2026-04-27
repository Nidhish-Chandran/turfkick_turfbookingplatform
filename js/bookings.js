/**
 * TurfKick User Booking Dashboard
 */

const TurfKickBookings = {
    turfs: [],
    bookings: [],
    csrfToken: null,
    selectedTurfId: null,
    selectedSlotId: null,

    async init() {
        await this.fetchToken();
        await this.fetchUser();
        await this.fetchTurfs();
        await this.fetchBookings();
    },

    async fetchToken() {
        const response = await fetch('api/get_token.php', { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success') this.csrfToken = result.data.csrf_token;
    },

    async fetchUser() {
        const response = await fetch('api/get_user.php', { credentials: 'same-origin' });
        const result = await response.json();
        console.log('Get user response:', result);

        const success = result.success ?? (result.status === 'success');
        if (!success) {
            window.location.href = 'index.html';
            return;
        }

        const name = result.data.user_name || result.data.name || 'User';
        const email = result.data.user_email || result.data.email || '';
        const initials = name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();

        const title = document.querySelector('h1');
        const profile = document.querySelector('.user-profile');
        const profileInitials = document.getElementById('profileInitials');
        const profileName = document.getElementById('profileName');
        const profileEmail = document.getElementById('profileEmail');

        if (title) title.innerText = `Welcome Back, ${name}!`;
        if (profile) profile.innerText = initials;
        if (profileInitials) profileInitials.innerText = initials;
        if (profileName) profileName.innerText = name;
        if (profileEmail) profileEmail.innerText = email;
    },

    async fetchTurfs() {
        const response = await fetch('api/get_turfs.php', { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success') {
            this.turfs = result.data || [];
            this.renderTurfs();
        }
    },

    async fetchBookings() {
        const response = await fetch('api/get_bookings.php', { credentials: 'same-origin' });
        const result = await response.json();
        console.log('Get bookings response:', result);
        const success = result.success ?? (result.status === 'success');
        if (success) {
            this.bookings = result.bookings || result.data || [];
        } else {
            this.bookings = [];
            console.error('Failed to fetch bookings:', result.message || 'Unknown error');
        }
    },

    renderTurfs() {
        const container = document.getElementById('listings');
        if (!container) return;

        const query = (document.getElementById('search')?.value || '').toLowerCase();
        const sportFilter = this.currentFilter || 'all';
        const sortType = document.getElementById('sortSelect')?.value || 'none';

        let filtered = this.turfs.filter(turf => {
            const name = String(turf.name || '').toLowerCase();
            const location = String(turf.location || '').toLowerCase();
            const sport = String(turf.sport_category || '');
            return (name.includes(query) || location.includes(query)) && (sportFilter === 'all' || sport === sportFilter);
        });

        if (sortType === 'price_low') filtered.sort((a, b) => Number(a.price_per_hour) - Number(b.price_per_hour));
        if (sortType === 'price_high') filtered.sort((a, b) => Number(b.price_per_hour) - Number(a.price_per_hour));

        container.innerHTML = filtered.map(turf => {
            const imageUrl = turf.image_path || (turf.gallery && turf.gallery[0]?.image_path) || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80';
            return `
            <div class="card" onclick="TurfKickBookings.openTurf(${Number(turf.id)})">
                <div class="card-img">
                    <img src="${this.escapeHtml(imageUrl)}" alt="${this.escapeHtml(turf.name)}">
                </div>
                <div class="card-body">
                    <h3 style="margin:0;">${this.escapeHtml(turf.name)}</h3>
                    <p style="opacity:0.6; font-size:13px; margin:5px 0;">${this.escapeHtml(turf.location || '')}</p>
                    <div style="color:var(--accent);">Rating 4.5</div>
                    <div class="card-price">Rs ${Number(turf.price_per_hour || 0).toFixed(2)} <small>/ hr</small></div>
                </div>
            </div>
        `}).join('');
    },

    setFilter(sport, btn) {
        this.currentFilter = sport;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        this.renderTurfs();
    },

    async openTurf(id) {
        const turf = this.turfs.find(t => Number(t.id) === Number(id));
        if (!turf) return;

        this.selectedTurfId = id;
        this.selectedSlotId = null;

        const imgSrc = turf.image_path || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80';
        document.getElementById('modalImg').style.backgroundImage = `url(${imgSrc})`;
        document.getElementById('modalName').innerText = turf.name;
        document.getElementById('modalLoc').innerText = turf.location;
        document.getElementById('modalRating').innerText = 'Rating 4.5';

        const dateInput = document.getElementById('bookingDate');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            dateInput.value = today;
        }

        this.renderGallery(turf);
        await this.renderSlots(id);
        await this.renderEquipment(id);
        this.updateBookingTotal();

        document.getElementById('bookingContext').style.display = 'block';
        document.getElementById('statusContext').style.display = 'none';

        const modal = document.getElementById('turfModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    },

    renderGallery(turf) {
        const container = document.getElementById('modalGallery');
        if (!container) return;

        const images = turf.gallery && turf.gallery.length ? turf.gallery : [{ image_path: turf.image_path }];
        container.innerHTML = images.filter(img => img.image_path).map(img => `
            <img src="${this.escapeHtml(img.image_path)}" alt="" style="width:100%; aspect-ratio:1; object-fit:cover; border-radius:8px; cursor:pointer;" onclick="document.getElementById('modalImg').style.backgroundImage='url(${this.escapeHtml(img.image_path)})'">
        `).join('');
    },

    async onDateChange() {
        this.selectedSlotId = null;
        if (this.selectedTurfId) await this.renderSlots(this.selectedTurfId);
    },

    async renderSlots(turfId) {
        const date = document.getElementById('bookingDate')?.value || new Date().toISOString().split('T')[0];
        const response = await fetch(`api/get_slots.php?turf_id=${Number(turfId)}&date=${encodeURIComponent(date)}`, { credentials: 'same-origin' });
        const result = await response.json();
        const container = document.getElementById('modalSlots');
        if (!container) return;

        if (result.status !== 'success' || !result.data.length) {
            container.innerHTML = '<p style="opacity:0.5;">No slots available for this date.</p>';
            return;
        }

        container.innerHTML = result.data.map(slot => `
            <div class="slot" data-slot-id="${Number(slot.id)}" onclick="TurfKickBookings.selectSlot(this, ${Number(turfId)}, ${Number(slot.id)})">
                ${this.escapeHtml(slot.slot_label)}
            </div>
        `).join('');
    },

    async renderEquipment(turfId) {
        const response = await fetch(`api/get_equipment.php?turf_id=${Number(turfId)}`, { credentials: 'same-origin' });
        const result = await response.json();
        const container = document.getElementById('accStore');
        if (!container) return;

        if (result.status !== 'success' || !result.data.length) {
            container.innerHTML = '<p style="opacity:0.5; font-size:12px;">No additional equipment available for this turf.</p>';
            return;
        }

        container.innerHTML = result.data.map(item => `
            <div class="acc-item">
                <span>${this.escapeHtml(item.name)}</span>
                <label><input type="checkbox" value="${Number(item.id)}" data-price="${Number(item.price_per_session || 0)}" onchange="TurfKickBookings.updateBookingTotal()"> +Rs ${Number(item.price_per_session || 0).toFixed(2)}</label>
            </div>
        `).join('');
    },

    selectSlot(el, turfId, slotId) {
        if (el.classList.contains('booked')) return;
        document.querySelectorAll('.slot').forEach(slot => slot.classList.remove('selected'));
        el.classList.add('selected');
        this.selectedTurfId = turfId;
        this.selectedSlotId = slotId;
        this.checkAvailability(turfId, slotId);
    },

    async checkAvailability(turfId, slotId) {
        const date = document.getElementById('bookingDate')?.value || new Date().toISOString().split('T')[0];
        const response = await fetch(`api/check_availability.php?turf_id=${Number(turfId)}&slot_id=${Number(slotId)}&date=${encodeURIComponent(date)}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success' && !result.data.available) {
            const el = document.querySelector(`.slot[data-slot-id="${Number(slotId)}"]`);
            if (el) {
                el.classList.add('booked');
                el.classList.remove('selected');
            }
            this.selectedSlotId = null;
            alert('This slot is already booked.');
        }
    },

    updateBookingTotal() {
        const turf = this.turfs.find(t => Number(t.id) === Number(this.selectedTurfId));
        const base = turf ? Number(turf.price_per_hour || 0) : 0;
        const equipmentTotal = Array.from(document.querySelectorAll('#accStore input[type="checkbox"]:checked'))
            .reduce((sum, input) => sum + Number(input.dataset.price || 0), 0);
        const total = base + equipmentTotal;
        const totalEl = document.getElementById('bookingTotal');
        if (totalEl) totalEl.innerText = `Total: Rs ${total.toFixed(2)}`;
    },

    async bookNow() {
        if (!this.selectedSlotId) return alert('Select a time slot.');

        const date = document.getElementById('bookingDate')?.value;
        if (!date) return alert('Select a booking date.');

        const selectedEquipment = Array.from(document.querySelectorAll('#accStore input[type="checkbox"]:checked')).map(input => input.value);
        const formData = new FormData();
        formData.append('turf_id', this.selectedTurfId);
        formData.append('slot_id', this.selectedSlotId);
        formData.append('date', date);
        formData.append('equipment_ids', JSON.stringify(selectedEquipment));
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/create_booking.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success') {
            alert(result.message);
            this.closeModal('turfModal');
            await this.fetchBookings();
        } else {
            alert('Booking failed: ' + result.message);
        }
    },

    closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    },

    openModal(id) {
        if (id === 'historyModal') {
            this.renderLogs();
        }
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    },

    renderLogs() {
        const list = document.getElementById('historyList');
        if (!list) return;
        if (!this.bookings || this.bookings.length === 0) {
            list.innerHTML = '<p style="opacity:0.6;">No bookings found.</p>';
            return;
        }

        list.innerHTML = this.bookings.map(booking => {
            const status = booking.status === 'cancelled' ? 'cancelled' : (booking.display_status || booking.status || 'upcoming');
            const canCancel = status !== 'cancelled';
            const isUpcoming = status === 'upcoming';
            return `
            <div class="log-item" style="cursor: pointer;" onclick="TurfKickBookings.openBooking(${Number(booking.id)})">
                <div style="flex:1;">
                    <strong style="display:block;">${this.escapeHtml(booking.turf_name)}</strong>
                    <small style="opacity:0.6;">${this.escapeHtml(booking.booking_date)} - ${this.escapeHtml(booking.slot_label)}</small>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="tag tag-${this.escapeHtml(status)}">${this.escapeHtml(status)}</span>
                    ${isUpcoming ? `<button class="btn btn-outline" style="padding:8px 12px; min-width:88px;" onclick="event.stopPropagation(); TurfKickBookings.completeBooking(${Number(booking.id)});">Complete</button>` : ''}
                    ${canCancel ? `<button class="btn btn-outline" style="padding:8px 12px; min-width:120px;" onclick="event.stopPropagation(); TurfKickBookings.cancelBooking(${Number(booking.id)});">Cancel Booking</button>` : ''}
                </div>
            </div>
            `;
        }).join('');
    },

    async openBooking(id) {
        const booking = this.bookings.find(item => Number(item.id) === Number(id));
        if (!booking) return;

        document.getElementById('modalImg').style.backgroundImage = `url(${booking.image_path || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=800&q=80'})`;
        document.getElementById('modalName').innerText = booking.turf_name;
        document.getElementById('modalLoc').innerText = booking.location;
        document.getElementById('modalRating').innerText = 'Rating 4.5';

        const displayStatus = booking.status === 'cancelled' ? 'cancelled' : (booking.display_status || booking.status || 'upcoming');
        document.getElementById('bookingContext').style.display = 'none';
        document.getElementById('statusContext').style.display = 'block';
        document.getElementById('statusArea').innerHTML = `
            <span class="tag tag-${this.escapeHtml(displayStatus)}">${this.escapeHtml(displayStatus)}</span>
            <div style="font-weight:700; margin-top:10px;">${this.escapeHtml(booking.booking_date)} at ${this.escapeHtml(booking.slot_label)}</div>
        `;

        const cancelBtn = document.getElementById('cancelBtn');
        if (displayStatus !== 'cancelled') {
            cancelBtn.style.display = 'block';
            cancelBtn.innerText = 'Cancel Booking';
            cancelBtn.onclick = () => this.cancelBooking(booking.id);
        } else {
            cancelBtn.style.display = 'none';
        }

        this.closeModal('historyModal');
        const modal = document.getElementById('turfModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    },

    async cancelBooking(bookingId) {
        if (!confirm('Cancel this booking?')) return;

        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/cancel_booking.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        const success = result.success ?? (result.status === 'success');
        if (success) {
            alert(result.message);
            const booking = this.bookings.find(item => Number(item.id) === Number(bookingId));
            if (booking) {
                booking.status = 'cancelled';
                booking.display_status = 'cancelled';
            }

            if (document.getElementById('historyModal')?.classList.contains('active')) {
                this.renderLogs();
            }

            const statusArea = document.getElementById('statusArea');
            const cancelBtn = document.getElementById('cancelBtn');
            if (statusArea && booking) {
                statusArea.innerHTML = `
                    <span class="tag tag-cancelled">CANCELLED</span>
                    <div style="font-weight:700; margin-top:10px;">${this.escapeHtml(booking.booking_date)} at ${this.escapeHtml(booking.slot_label)}</div>
                `;
            }
            if (cancelBtn) {
                cancelBtn.style.display = 'none';
            }
        } else {
            alert('Cancel failed: ' + result.message);
        }
    },

    async completeBooking(bookingId) {
        if (!confirm('Mark this booking as completed?')) return;

        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/complete_booking.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        const success = result.success ?? (result.status === 'success');
        if (success) {
            alert(result.message);
            await this.fetchBookings();
            if (document.getElementById('historyModal')?.classList.contains('active')) {
                this.renderLogs();
            }
        } else {
            alert('Complete failed: ' + result.message);
        }
    },

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

document.addEventListener('DOMContentLoaded', () => TurfKickBookings.init());

window.bookNow = () => TurfKickBookings.bookNow();
window.openModal = id => TurfKickBookings.openModal(id);
window.closeModal = id => TurfKickBookings.closeModal(id);
window.renderLogs = () => TurfKickBookings.renderLogs();
