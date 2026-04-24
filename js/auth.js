/**
 * TurfKick AJAX Auth Helper
 */

const TurfKickAuth = {
    csrfToken: null,

    /**
     * Fetch CSRF token from the server
     */
    async fetchToken() {
        try {
            const response = await fetch('api/get_token.php');
            const result = await response.json();
            if (result.status === 'success') {
                this.csrfToken = result.data.csrf_token;
                return this.csrfToken;
            }
        } catch (error) {
            console.error('Error fetching CSRF token:', error);
        }
        return null;
    },

    /**
     * Submit form using AJAX
     */
    async submitForm(formId, endpoint, onSuccess, onValidate) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (onValidate && !onValidate()) {
                return;
            }

            if (!this.csrfToken) {
                await this.fetchToken();
            }

            const formData = new FormData(form);
            formData.append('csrf_token', this.csrfToken);

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    if (onSuccess) {
                        onSuccess(result);
                    } else {
                        alert(result.message);
                        if (result.data && result.data.redirect) {
                            window.location.href = result.data.redirect;
                        }
                    }
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('AJAX Error:', error);
                alert('An error occurred. Please try again.');
            }
        });
    }
};

// Initialize CSRF token on load
document.addEventListener('DOMContentLoaded', () => {
    TurfKickAuth.fetchToken();
});
