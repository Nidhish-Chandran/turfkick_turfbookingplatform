/**
 * TurfKick Admin Dashboard JS
 */

const TurfKickAdmin = {
    csrfToken: null,

    async init() {
        await this.fetchToken();
        this.fetchUsers();
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

    async fetchUsers() {
        try {
            const response = await fetch('api/admin/get_users.php');
            const result = await response.json();
            const tbody = document.querySelector('#userTable tbody');
            if (result.status === 'success') {
                tbody.innerHTML = result.data.length ? result.data.map(u => `
                    <tr>
                        <td>${this.escapeHtml(u.name)}</td>
                        <td>${this.escapeHtml(u.email)}${u.phone ? `<br><small>${this.escapeHtml(u.phone)}</small>` : ''}</td>
                        <td><span class="status-pill pill-${this.escapeHtml(u.role)}">${this.escapeHtml(u.role)}</span></td>
                        <td>${u.role === 'owner' ? `${Number(u.turf_count || 0)} turf(s)` : '---'}</td>
                        <td>${this.formatDate(u.created_at)}</td>
                        <td>
                            ${u.role !== 'admin' ? `<button class="btn btn-delete" onclick="TurfKickAdmin.deleteUser(${Number(u.id)})">Delete</button>` : '---'}
                        </td>
                    </tr>
                `).join('') : this.emptyRow(6, 'No users found.');
            } else {
                tbody.innerHTML = this.emptyRow(6, result.message || 'Unable to load users.');
            }
        } catch (error) {
            console.error('Error fetching users:', error);
            document.querySelector('#userTable tbody').innerHTML = this.emptyRow(6, 'Unable to load users.');
        }
    },

    async deleteUser(userId) {
        if (!confirm("Are you sure you want to delete this user? This will mark them as deleted.")) return;

        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/admin/delete_user.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            this.fetchUsers();
            this.fetchTurfs();
        } catch (error) {
            console.error('Error deleting user:', error);
        }
    },

    async fetchTurfs() {
        try {
            const response = await fetch('api/admin/get_turfs.php');
            const result = await response.json();
            const tbody = document.querySelector('#turfTable tbody');
            if (result.status === 'success') {
                tbody.innerHTML = result.data.length ? result.data.map(t => `
                    <tr>
                        <td>
                            <strong>${this.escapeHtml(t.name)}</strong><br>
                            <small>${this.escapeHtml(t.sport_category || 'Not specified')} - Rs ${Number(t.price_per_hour || 0).toFixed(2)}/hour</small>
                        </td>
                        <td>
                            ${this.escapeHtml(t.owner_name || 'Unknown owner')} ${Number(t.owner_deleted || 0) === 1 ? '<span class="status-pill pill-inactive">deleted</span>' : ''}<br>
                            <small>${this.escapeHtml(t.owner_email || '')}</small>
                        </td>
                        <td>${this.escapeHtml(t.location || 'Not specified')}</td>
                        <td><span class="status-pill pill-${this.escapeHtml(t.status)}">${this.escapeHtml(t.status)}</span></td>
                        <td>
                            ${this.turfActions(t)}
                        </td>
                    </tr>
                `).join('') : this.emptyRow(5, 'No turfs found.');
            } else {
                tbody.innerHTML = this.emptyRow(5, result.message || 'Unable to load turfs.');
            }
        } catch (error) {
            console.error('Error fetching turfs:', error);
            document.querySelector('#turfTable tbody').innerHTML = this.emptyRow(5, 'Unable to load turfs.');
        }
    },

    async toggleTurf(turfId, status) {
        const action = status === 'active' ? 'enable' : 'block';
        if (!confirm(`Are you sure you want to ${action} this turf?`)) return;

        const formData = new FormData();
        formData.append('turf_id', turfId);
        formData.append('status', status);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/admin/toggle_turf.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            this.fetchTurfs();
            this.fetchBookings();
        } catch (error) {
            console.error('Error toggling turf:', error);
        }
    },

    async fetchBookings() {
        try {
            const response = await fetch('api/admin/get_bookings.php');
            const result = await response.json();
            const tbody = document.querySelector('#bookingTable tbody');
            if (result.status === 'success') {
                tbody.innerHTML = result.data.length ? result.data.map(b => `
                    <tr>
                        <td>${this.escapeHtml(b.user_name || 'Deleted user')}<br><small>${this.escapeHtml(b.user_email || '')}</small></td>
                        <td>
                            <strong>${this.escapeHtml(b.turf_name || 'Deleted turf')}</strong><br>
                            <small>${this.escapeHtml(b.turf_location || '')}</small>
                        </td>
                        <td>${this.escapeHtml(b.owner_name || 'Unknown owner')}<br><small>${this.escapeHtml(b.owner_email || '')}</small></td>
                        <td>${this.escapeHtml(b.booking_date || '')}</td>
                        <td>${this.escapeHtml(b.slot_label || 'Slot removed')}</td>
                        <td>Rs ${Number(b.total_price || 0).toFixed(2)}</td>
                        <td><span class="status-pill pill-${this.escapeHtml(b.status)}">${this.escapeHtml(b.status)}</span></td>
                        <td>
                            ${b.status === 'upcoming' ? `<button class="btn btn-cancel" onclick="TurfKickAdmin.cancelBooking(${Number(b.id)})">Cancel</button>` : '---'}
                        </td>
                    </tr>
                `).join('') : this.emptyRow(8, 'No bookings found.');
            } else {
                tbody.innerHTML = this.emptyRow(8, result.message || 'Unable to load bookings.');
            }
        } catch (error) {
            console.error('Error fetching bookings:', error);
            document.querySelector('#bookingTable tbody').innerHTML = this.emptyRow(8, 'Unable to load bookings.');
        }
    },

    async cancelBooking(bookingId) {
        if (!confirm("Are you sure you want to cancel this booking as admin?")) return;

        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/admin/cancel_booking.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            this.fetchBookings();
        } catch (error) {
            console.error('Error cancelling booking:', error);
        }
    },

    turfActions(turf) {
        if (Number(turf.owner_deleted || 0) === 1) {
            return 'Owner deleted';
        }

        if (turf.status === 'active') {
            return `<button class="btn btn-disable" onclick="TurfKickAdmin.toggleTurf(${Number(turf.id)}, 'inactive')">Block</button>`;
        }

        if (turf.status === 'inactive') {
            return `<button class="btn btn-approve" onclick="TurfKickAdmin.toggleTurf(${Number(turf.id)}, 'active')">Enable</button>`;
        }

        if (turf.status === 'pending') {
            return `
                <button class="btn btn-approve" onclick="TurfKickAdmin.toggleTurf(${Number(turf.id)}, 'active')">Approve</button>
                <button class="btn btn-disable" onclick="TurfKickAdmin.toggleTurf(${Number(turf.id)}, 'inactive')">Block</button>
            `;
        }

        return '---';
    },

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    emptyRow(colspan, message) {
        return `<tr><td colspan="${colspan}" class="empty-state">${this.escapeHtml(message)}</td></tr>`;
    },

    formatDate(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? this.escapeHtml(value) : date.toLocaleDateString();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    TurfKickAdmin.init();
});

// Expose to global scope for HTML
window.toggleTurf = (id, status) => TurfKickAdmin.toggleTurf(id, status);
window.cancelBooking = (id) => TurfKickAdmin.cancelBooking(id);
window.deleteUser = (id) => TurfKickAdmin.deleteUser(id);
