<script>
    async function authenticateWithPasskey(remember = false) {
        const response = await fetch(@js(route('passkeys.authentication_options', [], false)))

        const options = await response.json();

        const startAuthenticationResponse = await startAuthentication({ optionsJSON: options, });

        const form = document.getElementById('passkey-login-form');
    
        form.addEventListener('formdata', ({formData}) => {
            formData.set('remember', remember);
            formData.set('start_authentication_response', JSON.stringify(startAuthenticationResponse));
        });

        form.submit();
    }
</script>
