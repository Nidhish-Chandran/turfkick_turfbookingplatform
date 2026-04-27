/**
 * TurfKick Owner Dashboard
 */

const TurfKickOwner = {
    turfs: [],
    equipment: [],
    currentTurf: null,
    csrfToken: null,
    copySourceDay: null,
    days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
    schedule: {},

    async init() {
        this.days.forEach(day => this.schedule[day] = []);
        await this.fetchToken();
        await this.fetchTurfs();
        await this.fetchEquipment();
        await this.fetchBookings();

        window.switchManageTab = (tab, btn) => this.switchManageTab(tab, btn);
        window.addSlotToDay = day => this.addSlotToDay(day);
        window.saveDaySchedule = day => this.saveDaySchedule(day);
        window.openCopyModal = day => this.openCopyModal(day);
        window.confirmCopy = () => this.confirmCopy();
        window.addEquipment = () => this.addEquipment();
        window.updateProfile = () => this.updateProfile();
    },

    async fetchToken() {
        const response = await fetch('api/get_token.php', { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success') this.csrfToken = result.data.csrf_token;
    },

    async fetchTurfs() {
        const response = await fetch('api/manage_turfs.php', { credentials: 'same-origin' });
        const result = await response.json();
        console.log('Fetch turfs response:', result);
        if (result.status !== 'success') {
            this.showListMessage(result.message || 'Unable to load turfs.');
            return;
        }

        this.turfs = result.data || [];
        this.renderTurfList();
    },

    renderTurfList() {
        const container = document.getElementById('turfListGrid');
        if (!container) return;

        if (this.turfs.length === 0) {
            this.showListMessage('No properties listed.');
            return;
        }

        container.innerHTML = this.turfs.map(turf => {
            const imageUrl = turf.image_path || (turf.gallery && turf.gallery[0]?.image_path) || 'https://images.unsplash.com/photo-1508098682722-e99c643e7f0b?auto=format&fit=crop&w=400&q=80';
            return `
            <div class="turf-card">
                <img src="${this.escapeHtml(imageUrl)}" alt="${this.escapeHtml(turf.name)}">
                <div class="turf-card-body">
                    <div style="display:flex; justify-content:space-between; gap:10px;">
                        <h4 style="margin:0;">${this.escapeHtml(turf.name)}</h4>
                        <span class="status-pill" style="font-size:9px; background:rgba(255,255,255,0.1); padding:2px 6px; border-radius:4px;">${this.escapeHtml(turf.status)}</span>
                    </div>
                    <p style="opacity:0.6; font-size:12px; margin:5px 0;">${this.escapeHtml(turf.location || 'No location')}</p>
                    <div style="margin-top:15px;">
                        <button class="btn btn-primary" style="width:100%;" onclick="TurfKickOwner.manageTurf(${Number(turf.id)})">Manage Property</button>
                    </div>
                </div>
            </div>
        `}).join('');
    },

    showListMessage(message) {
        const container = document.getElementById('turfListGrid');
        if (container) container.innerHTML = `<p style="opacity:0.5; grid-column:1/-1; text-align:center;">${this.escapeHtml(message)}</p>`;
    },

    async manageTurf(id) {
        const response = await fetch(`api/manage_turfs.php?turf_id=${Number(id)}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status !== 'success' || !result.data) {
            alert(result.message || 'Unable to open turf.');
            return;
        }

        this.currentTurf = result.data;
        this.renderManageView();
        if (typeof showSection === 'function') showSection('manage-turf');
        this.switchManageTab('details', document.querySelector("[onclick*=\"switchManageTab('details'\"]"));
    },

    renderManageView() {
        const turf = this.currentTurf;
        document.getElementById('managingTurfName').innerText = turf.name;
        document.getElementById('edit_turfId').value = turf.id;
        document.getElementById('edit_turfName').value = turf.name || '';
        document.getElementById('edit_turfPrice').value = turf.price_per_hour || 0;
        document.getElementById('edit_turfSport').value = turf.sport_category || 'Football';
        document.getElementById('edit_turfLoc').value = turf.location || '';
        document.getElementById('edit_turfDesc').value = turf.description || '';

        const mStatus = document.getElementById('currentMStatus');
        if (mStatus) {
            mStatus.innerText = Number(turf.is_under_maintenance || 0) === 1 ? 'Under Maintenance' : 'Active & Live';
            mStatus.style.color = Number(turf.is_under_maintenance || 0) === 1 ? 'var(--danger)' : 'var(--secondary)';
        }

        this.renderGallery();
        this.renderEquipment();
        this.fetchSlots();
        this.fetchMaintenanceRequests(turf.id);
    },

    async updateTurf() {
        if (!this.currentTurf) return;

        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('turf_id', this.currentTurf.id);
        formData.append('name', document.getElementById('edit_turfName').value);
        formData.append('price', document.getElementById('edit_turfPrice').value);
        formData.append('location', document.getElementById('edit_turfLoc').value);
        formData.append('category', document.getElementById('edit_turfSport').value);
        formData.append('description', document.getElementById('edit_turfDesc').value);
        formData.append('csrf_token', this.csrfToken);

        const file = document.getElementById('edit_turfImage').files[0];
        if (file) formData.append('image', file);

        const response = await fetch('api/manage_turfs.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);

        if (result.status === 'success') {
            document.getElementById('edit_turfImage').value = '';
            await this.fetchTurfs();
            await this.manageTurf(this.currentTurf.id);
        }
    },

    renderGallery() {
        const container = document.getElementById('galleryGrid');
        if (!container || !this.currentTurf) return;

        const gallery = this.currentTurf.gallery || [];
        if (gallery.length === 0) {
            container.innerHTML = '<p style="opacity:0.5; font-size:12px;">No images yet.</p>';
            return;
        }

        container.innerHTML = gallery.map(image => `
            <div style="position:relative; border-radius:10px; overflow:hidden; aspect-ratio:1; border:1px solid var(--border);">
                <img src="${this.escapeHtml(image.image_path)}" style="width:100%; height:100%; object-fit:cover;" alt="">
                <button class="slot-delete" style="opacity:1; top:5px; right:5px; border:0;" onclick="TurfKickOwner.deleteGalleryImage(${Number(image.id)})">x</button>
            </div>
        `).join('');
    },

    async uploadGalleryImages(files) {
        if (!this.currentTurf || !files || files.length === 0) return;

        const formData = new FormData();
        formData.append('action', 'upload_gallery');
        formData.append('turf_id', this.currentTurf.id);
        formData.append('csrf_token', this.csrfToken);
        Array.from(files).forEach(file => formData.append('images[]', file));

        const response = await fetch('api/manage_turfs.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);
        document.getElementById('galleryUploadInput').value = '';

        if (result.status === 'success') {
            await this.manageTurf(this.currentTurf.id);
            this.switchManageTab('gallery', document.querySelector("[onclick*=\"switchManageTab('gallery'\"]"));
        }
    },

    async deleteGalleryImage(imageId) {
        if (!confirm('Delete this image?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_image');
        formData.append('image_id', imageId);
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/manage_turfs.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);

        if (result.status === 'success') {
            await this.manageTurf(this.currentTurf.id);
            this.switchManageTab('gallery', document.querySelector("[onclick*=\"switchManageTab('gallery'\"]"));
        }
    },

    async fetchSlots() {
        if (!this.currentTurf) return;

        const response = await fetch(`api/manage_slots.php?turf_id=${this.currentTurf.id}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status !== 'success') return;

        this.days.forEach(day => this.schedule[day] = []);
        (result.data || []).forEach(slot => {
            const day = slot.day_of_week || 'Monday';
            if (!this.schedule[day]) this.schedule[day] = [];
            this.schedule[day].push({
                name: slot.slot_name || '',
                start: String(slot.start_time || '').slice(0, 5),
                end: String(slot.end_time || '').slice(0, 5)
            });
        });

        this.renderWeeklySchedule();
    },

    renderWeeklySchedule() {
        const container = document.getElementById('manageSlotsList');
        if (!container) return;

        container.innerHTML = this.days.map(day => {
            const slots = this.schedule[day] || [];
            return `
                <div class="day-card ${slots.length ? '' : 'disabled'}" id="card-${day}">
                    <div class="day-header">
                        <div class="day-title">${day}</div>
                        <div style="display:flex; gap:10px;">
                            ${slots.length ? `<button class="copy-btn" onclick="openCopyModal('${day}')">Copy</button>` : ''}
                            <button class="btn btn-outline" style="font-size:11px; padding:5px 12px;" onclick="addSlotToDay('${day}')">+ Slot</button>
                        </div>
                    </div>
                    <div class="day-slots">
                        ${slots.map((slot, index) => `
                            <div class="slot-row">
                                <input type="text" placeholder="Name" value="${this.escapeHtml(slot.name)}" class="slot-name">
                                <input type="time" value="${this.escapeHtml(slot.start)}" class="slot-start">
                                <input type="time" value="${this.escapeHtml(slot.end)}" class="slot-end">
                                <span style="color:var(--danger); cursor:pointer; font-weight:bold;" onclick="TurfKickOwner.removeSlotUI('${day}', ${index})">x</span>
                            </div>
                        `).join('')}
                    </div>
                    <div style="text-align:right; margin-top:15px;">
                        <button class="btn btn-primary" style="font-size:10px;" onclick="saveDaySchedule('${day}')">Save Day</button>
                    </div>
                </div>
            `;
        }).join('');
    },

    addSlotToDay(day) {
        this.schedule[day].push({ name: '', start: '09:00', end: '10:00' });
        this.renderWeeklySchedule();
    },

    removeSlotUI(day, index) {
        this.schedule[day].splice(index, 1);
        this.renderWeeklySchedule();
    },

    async saveDaySchedule(day) {
        const card = document.getElementById(`card-${day}`);
        const slots = Array.from(card.querySelectorAll('.slot-row')).map(row => ({
            name: row.querySelector('.slot-name').value,
            start: row.querySelector('.slot-start').value,
            end: row.querySelector('.slot-end').value
        }));

        const formData = new FormData();
        formData.append('action', 'save_day');
        formData.append('turf_id', this.currentTurf.id);
        formData.append('day', day);
        formData.append('slots', JSON.stringify(slots));
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/manage_slots.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') this.fetchSlots();
    },

    openCopyModal(day) {
        this.copySourceDay = day;
        document.getElementById('copySourceText').innerText = `Copy ${day}'s schedule to:`;
        document.getElementById('targetDaysList').innerHTML = this.days.filter(d => d !== day).map(d => `
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; background:rgba(255,255,255,0.05); padding:10px; border-radius:10px;">
                <input type="checkbox" name="targetDay" value="${d}"> ${d}
            </label>
        `).join('');
        if (typeof openModal === 'function') openModal('copyModal');
    },

    async confirmCopy() {
        const selected = Array.from(document.querySelectorAll('input[name="targetDay"]:checked')).map(input => input.value);
        if (selected.length === 0) return alert('Select at least one day.');

        const formData = new FormData();
        formData.append('action', 'copy_to_days');
        formData.append('turf_id', this.currentTurf.id);
        formData.append('source_day', this.copySourceDay);
        formData.append('target_days', JSON.stringify(selected));
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/manage_slots.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') {
            if (typeof closeModal === 'function') closeModal('copyModal');
            this.fetchSlots();
        }
    },

    async addNewTurf() {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('name', document.getElementById('add_turfName').value);
        formData.append('price', document.getElementById('add_turfPrice').value);
        formData.append('location', document.getElementById('add_turfLoc').value);
        formData.append('category', document.getElementById('add_turfSport').value);
        formData.append('description', document.getElementById('add_turfDesc').value);
        formData.append('csrf_token', this.csrfToken);

        const file = document.getElementById('add_turfImage').files[0];
        if (file) formData.append('image', file);

        const response = await fetch('api/manage_turfs.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') {
            document.getElementById('addTurfForm').reset();
            await this.fetchTurfs();
            if (typeof showSection === 'function') showSection('my-turfs');
        }
    },

    switchManageTab(tab, btn) {
        document.querySelectorAll('.manage-tab').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-link').forEach(el => el.classList.remove('active'));
        const panel = document.getElementById('tab-' + tab);
        if (panel) panel.classList.add('active');
        if (btn) btn.classList.add('active');
    },

    async fetchEquipment() {
        const response = await fetch('api/manage_equipment.php', { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success') {
            this.equipment = result.data || [];
            this.renderEquipment();
        }
    },

    renderEquipment() {
        const html = this.equipment.length
            ? this.equipment.map(item => `
                <div class="item-row">
                    <div><strong>${this.escapeHtml(item.name)}</strong><br><small>Rs ${Number(item.price_per_session || 0).toFixed(2)}</small></div>
                    <button class="btn btn-danger" style="font-size:11px; padding:7px 10px;" onclick="TurfKickOwner.deleteEquipment(${Number(item.id)})">Remove</button>
                </div>
            `).join('')
            : '<p style="opacity:0.5; text-align:center;">No equipment added.</p>';

        const list = document.getElementById('itemList');
        const manageList = document.getElementById('manageItemList');
        if (list) list.innerHTML = html;
        if (manageList) manageList.innerHTML = html;
    },

    async addEquipment() {
        const name = document.getElementById('itemName').value.trim();
        const price = document.getElementById('itemPrice').value || 0;
        if (!name) return alert('Enter an equipment name.');

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('name', name);
        formData.append('price', price);
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/manage_equipment.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') {
            document.getElementById('itemName').value = '';
            document.getElementById('itemPrice').value = '';
            if (typeof closeModal === 'function') closeModal('itemModal');
            this.fetchEquipment();
        }
    },

    async deleteEquipment(itemId) {
        if (!confirm('Remove this equipment?')) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('item_id', itemId);
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/manage_equipment.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') this.fetchEquipment();
    },

    async fetchBookings() {
        const response = await fetch('api/owner/get_bookings.php', { credentials: 'same-origin' });
        const result = await response.json();
        const container = document.getElementById('bookingBody');
        if (!container) return;

        if (result.status !== 'success' || !result.data.length) {
            container.innerHTML = '<tr><td colspan="6" style="text-align:center; opacity:0.5;">No bookings yet.</td></tr>';
            return;
        }

        container.innerHTML = result.data.map(booking => `
            <tr>
                <td>${this.escapeHtml(booking.user_name)}</td>
                <td>${this.escapeHtml(booking.booking_date)}</td>
                <td>${this.escapeHtml(booking.slot_label)}</td>
                <td>${(booking.equipment_names || []).map(name => this.escapeHtml(name)).join(', ') || 'None'}</td>
                <td>${this.escapeHtml(booking.status)}</td>
                <td>---</td>
            </tr>
        `).join('');
    },

    async requestMaintenance() {
        const formData = new FormData();
        formData.append('turf_id', this.currentTurf.id);
        formData.append('start_date', document.getElementById('m_startDate').value);
        formData.append('end_date', document.getElementById('m_endDate').value);
        formData.append('reason', document.getElementById('m_reason').value);
        formData.append('csrf_token', this.csrfToken);

        const response = await fetch('api/maintenance_requests.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') this.fetchMaintenanceRequests(this.currentTurf.id);
    },

    async fetchMaintenanceRequests(id) {
        const list = document.getElementById('m_requestList');
        if (!list) return;

        const response = await fetch(`api/maintenance_requests.php?turf_id=${id}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success' && result.data.length > 0) {
            list.innerHTML = result.data.map(req => `
                <div style="padding:10px; margin-bottom:5px; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:8px;">
                    <small>${this.escapeHtml(req.start_date)} to ${this.escapeHtml(req.end_date)}</small>
                    <span class="status-pill pill-${this.escapeHtml(req.status)}" style="font-size:8px;">${this.escapeHtml(req.status)}</span>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<small style="opacity:0.5;">No history.</small>';
        }
    },

    updateProfile() {
        alert('Profile update is not implemented yet.');
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

document.addEventListener('DOMContentLoaded', () => TurfKickOwner.init());
window.TurfKickOwner = TurfKickOwner;
