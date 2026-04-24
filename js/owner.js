/**
 * TurfKick Owner Dashboard JS
 */

const TurfKickOwner = {
    turfs: [],
    bookings: [],
    csrfToken: null,

    async init() {
        await this.fetchToken();
        await this.fetchTurf();
        // Fetch bookings for owner would be here
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

    async fetchTurf() {
        try {
            const response = await fetch('api/manage_turfs.php');
            const result = await response.json();
            if (result.status === 'success' && result.data.length > 0) {
                const turf = result.data[0]; // Assuming one turf per owner for now
                this.currentTurfId = turf.id;
                
                document.getElementById('turfName').value = turf.name;
                document.getElementById('turfPrice').value = turf.price_per_hour;
                document.getElementById('turfLoc').value = turf.location;
                document.getElementById('turfSport').value = turf.sport_category;
                document.getElementById('turfDesc').value = turf.description;

                document.getElementById('displayName').innerText = turf.name;
                document.getElementById('displayPrice').innerText = '₹' + turf.price_per_hour + ' / hour';
                document.getElementById('displayLoc').innerText = turf.location;
                document.getElementById('turfDisplay').style.display = 'block';
            }
        } catch (error) {
            console.error('Error fetching turf:', error);
        }
    },

    async saveTurf() {
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('turf_id', this.currentTurfId);
        formData.append('name', document.getElementById('turfName').value);
        formData.append('price', document.getElementById('turfPrice').value);
        formData.append('location', document.getElementById('turfLoc').value);
        formData.append('category', document.getElementById('turfSport').value);
        formData.append('description', document.getElementById('turfDesc').value);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await fetch('api/manage_turfs.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.status === 'success') {
                this.fetchTurf();
            }
        } catch (error) {
            console.error('Error saving turf:', error);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    TurfKickOwner.init();
});
