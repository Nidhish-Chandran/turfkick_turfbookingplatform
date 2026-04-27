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
            const response = await fetch('api/get_token.php', {
                credentials: 'same-origin'
            });
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

            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton ? submitButton.textContent : '';

            if (onValidate && !onValidate()) {
                return;
            }

            if (!this.csrfToken) {
                await this.fetchToken();
            }

            if (!this.csrfToken) {
                alert('Could not start a secure login session. Open the site through XAMPP, for example http://localhost/TurfKick/index.html.');
                return;
            }

            const formData = new FormData(form);
            formData.append('csrf_token', this.csrfToken);

            try {
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Logging in...';
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const text = await response.text();
                let result;

                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('Non-JSON auth response:', text);
                    alert('Login failed because the server returned an invalid response. Check PHP/MySQL errors.');
                    return;
                }

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
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            }
        });
    }
};

// Initialize CSRF token on load
document.addEventListener('DOMContentLoaded', () => {
    TurfKickAuth.fetchToken();
});
