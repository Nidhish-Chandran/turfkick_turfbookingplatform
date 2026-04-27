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
                tbody.innerHTML = result.data.map(u => `
                    <tr>
                        <td>${u.name}</td>
                        <td>${u.email}</td>
                        <td>${u.role}</td>
                        <td>${u.created_at}</td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error fetching users:', error);
        }
    },

    async fetchTurfs() {
        try {
            const response = await fetch('api/admin/get_turfs.php');
            const result = await response.json();
            const tbody = document.querySelector('#turfTable tbody');
            if (result.status === 'success') {
                tbody.innerHTML = result.data.map(t => `
                    <tr>
                        <td>${t.name}</td>
                        <td>${t.owner_name}</td>
                        <td>${t.location}</td>
                        <td><span class="status-pill pill-${t.status}">${t.status}</span></td>
                        <td>
                            <button class="btn" style="background:var(--secondary); color:var(--primary);" onclick="TurfKickAdmin.viewTurfDetails(${t.id})">View</button>
                            ${t.status === 'pending' ? `<button class="btn btn-approve" onclick="TurfKickAdmin.toggleTurf(${t.id}, 'active')">Approve</button>` : ''}
                            ${t.status === 'active' ? `<button class="btn btn-disable" onclick="TurfKickAdmin.toggleTurf(${t.id}, 'inactive')">Disable</button>` : ''}
                            ${t.status === 'inactive' ? `<button class="btn btn-approve" onclick="TurfKickAdmin.toggleTurf(${t.id}, 'active')">Enable</button>` : ''}
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error fetching turfs:', error);
        }
    },

    async viewTurfDetails(turfId) {
        try {
            const response = await fetch('api/admin/get_turfs.php');
            const result = await response.json();
            const turf = result.data.find(t => t.id == turfId);
            
            if (turf) {
                const modal = document.getElementById('detailsModal');
                const content = document.getElementById('detailsContent');
                
                content.innerHTML = `
                    <h3>${turf.name}</h3>
                    <p><strong>Owner:</strong> ${turf.owner_name}</p>
                    <p><strong>Location:</strong> ${turf.location}</p>
                    <p><strong>Category:</strong> ${turf.sport_category}</p>
                    <p><strong>Price:</strong> ₹${turf.price_per_hour}/hr</p>
                    <p><strong>Description:</strong> ${turf.description || 'No description'}</p>
                    <p><strong>Status:</strong> ${turf.status}</p>
                    <hr>
                    <h4>Recent Bookings for this Turf</h4>
                    <div id="turfBookingList">Loading...</div>
                `;
                
                modal.style.display = 'flex';
                
                // Fetch bookings for this specific turf
                const bResp = await fetch('api/admin/get_bookings.php');
                const bResult = await bResp.json();
                const turfBookings = bResult.data.filter(b => b.turf_id == turfId);
                
                document.getElementById('turfBookingList').innerHTML = turfBookings.length ? `
                    <table>
                        <thead>
                            <tr><th>User</th><th>Date</th><th>Slot</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            ${turfBookings.map(b => `
                                <tr>
                                    <td>${b.user_name}</td>
                                    <td>${b.booking_date}</td>
                                    <td>${b.slot_label}</td>
                                    <td>${b.status}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                ` : '<p>No bookings found for this turf.</p>';
            }
        } catch (error) {
            console.error('Error viewing turf details:', error);
        }
    },

    closeDetailsModal() {
        document.getElementById('detailsModal').style.display = 'none';
    },

    async fetchComplaints() {
        try {
            const response = await fetch('api/get_complaints.php');
            const result = await response.json();
            const tbody = document.querySelector('#complaintTable tbody');
            if (result.status === 'success') {
                tbody.innerHTML = result.data.map(c => `
                    <tr>
                        <td>${c.user_name}</td>
                        <td>${c.turf_name}</td>
                        <td>${c.subject}</td>
                        <td><span class="status-pill pill-${c.status.toLowerCase().replace(' ', '-')}">${c.status}</span></td>
                        <td>
                            <button class="btn" style="background:var(--secondary); color:var(--primary);" onclick="TurfKickAdmin.viewComplaint(${c.id})">Review</button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error fetching complaints:', error);
        }
    },

    async viewComplaint(id) {
        try {
            const response = await fetch('api/get_complaints.php');
            const result = await response.json();
            const comp = result.data.find(c => c.id == id);
            if (comp) {
                const modal = document.getElementById('complaintModal');
                const content = document.getElementById('complaintContent');
                content.innerHTML = `
                    <p><strong>User:</strong> ${comp.user_name}</p>
                    <p><strong>Turf:</strong> ${comp.turf_name}</p>
                    <p><strong>Subject:</strong> ${comp.subject}</p>
                    <p><strong>Description:</strong> ${comp.description}</p>
                    <p><strong>Date:</strong> ${comp.created_at}</p>
                `;
                document.getElementById('compStatus').value = comp.status;
                document.getElementById('compResponse').value = comp.response || '';
                
                document.getElementById('updateCompBtn').onclick = () => this.updateComplaint(id);
                
                modal.style.display = 'flex';
            }
        } catch (error) {
            console.error('Error viewing complaint:', error);
        }
    },

    async updateComplaint(id) {
        const status = document.getElementById('compStatus').value;
        const responseText = document.getElementById('compResponse').value;

        const formData = new FormData();
        formData.append('complaint_id', id);
        formData.append('status', status);
        formData.append('response', responseText);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/update_complaint.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            document.getElementById('complaintModal').style.display = 'none';
            this.fetchComplaints();
        } catch (error) {
            console.error('Error updating complaint:', error);
        }
    },

    async toggleTurf(turfId, status) {
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
                tbody.innerHTML = result.data.map(b => `
                    <tr>
                        <td>${b.user_name}</td>
                        <td>${b.turf_name}</td>
                        <td>${b.booking_date}</td>
                        <td>${b.slot_label}</td>
                        <td><span class="status-pill pill-${b.status}">${b.status}</span></td>
                        <td>
                            ${b.status === 'upcoming' ? `<button class="btn btn-cancel" onclick="TurfKickAdmin.cancelBooking(${b.id})">Admin Cancel</button>` : '---'}
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error fetching bookings:', error);
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
    }
};

document.addEventListener('DOMContentLoaded', () => {
    TurfKickAdmin.init();
});

// Expose to global scope for HTML
window.toggleTurf = (id, status) => TurfKickAdmin.toggleTurf(id, status);
window.cancelBooking = (id) => TurfKickAdmin.cancelBooking(id);
