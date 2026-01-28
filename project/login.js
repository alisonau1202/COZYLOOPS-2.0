const { createApp, ref } = Vue;
const { createVuetify } = Vuetify;
const vuetify = createVuetify();

createApp({
	setup() {
		const email = ref('');
		const password = ref('');
		const message = ref('');
		const isSuccess = ref(false);
		const isError = ref(false);
		const isWelcome = ref(false); // Add welcome state
		const user = ref(null);
		const formRef = ref(null);
		const isSubmitting = ref(false);
		const showPassword = ref(false); // New ref for password visibility

		const rules = {
			email: [
				v => !!v || 'Email is required',
				v => /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(v) || 'Please enter a valid email address'
			],
			password: [
				v => !!v || 'Password is required',
				v => v.length >= 8 || 'Password must be at least 8 characters',
				v => /[A-Z]/.test(v) || 'Password must contain at least one uppercase letter',
				v => /[0-9]/.test(v) || 'Password must contain at least one number',
				v => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(v) || 'Password must contain at least one special character'
			]
		};

		const loginUser = async () => {
			const { valid } = await formRef.value.validate();
			if (!valid) {
				isError.value = true;
				isSuccess.value = false;
				isWelcome.value = false;
				message.value = "Please correct all form errors before submitting.";
				// Add shake animation to form
				const loginContainer = document.querySelector('.login-container');
				if (loginContainer) {
					loginContainer.classList.add('error-shake');
					setTimeout(() => {
						loginContainer.classList.remove('error-shake');
					}, 500);
				}
				return;
			}

			isSuccess.value = false;
			isError.value = false;
			isWelcome.value = false;
			message.value = '';
			isSubmitting.value = true;

			try {
				const response = await fetch('login.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ 
						email: email.value, 
						password: password.value
					})
				});

				const result = await response.json();

				if (response.ok) {
					user.value = result.user;
					message.value = result.message || 'Login successful!';
					isSuccess.value = true;
					
					// After 1 second, replace with welcome message and change styling
					setTimeout(() => {
						message.value = `Welcome, ${result.user.name || 'User'}!`;
						// Change to welcome styling
						isSuccess.value = false;
						isError.value = false;
						isWelcome.value = true; // Set welcome state
						
						// After another 2 seconds, redirect
						setTimeout(() => {
							window.location.href = result.redirectUrl || 'index.php';
						}, 2000);
					}, 1000);
					
				} else {
					message.value = result.message || 'Login failed';
					isError.value = true;
					isSuccess.value = false;
					isWelcome.value = false;
					
					// Add shake animation to form on error
					const loginContainer = document.querySelector('.login-container');
					if (loginContainer) {
						loginContainer.classList.add('error-shake');
						setTimeout(() => {
							loginContainer.classList.remove('error-shake');
						}, 500);
					}
				}
			} catch (error) {
				message.value = 'Network error: ' + error.message;
				isError.value = true;
				isSuccess.value = false;
				isWelcome.value = false;
				
				// Add shake animation to form on network error
				const loginContainer = document.querySelector('.login-container');
				if (loginContainer) {
					loginContainer.classList.add('error-shake');
					setTimeout(() => {
						loginContainer.classList.remove('error-shake');
					}, 500);
				}
			} finally {
				isSubmitting.value = false;
			}
		};

		const redirectToRegister = () => {
			window.location.href = 'registration.php';
		};

		// Function to get custom alert class based on message type
		const getAlertClass = () => {
			if (isError.value) return 'login-error-message';
			if (isSuccess.value) return 'login-success-message';
			if (isWelcome.value) return 'welcome-message-elegant'; // Use existing welcome styling
			return '';
		};

		return {
			email,
			password,
			message,
			isSuccess,
			isError,
			isWelcome,
			user,
			formRef,
			rules,
			loginUser,
			isSubmitting,
			redirectToRegister,
			getAlertClass,
			showPassword // Expose showPassword
		};
	},
	template: `
		<v-app>
			<v-main style="padding: 0 !important;">
				<div class="login-wrapper">
					<!-- Left side - Login Form -->
					<div class="login-form-section">
						<div class="login-container">
							<h1 class="login-title">Login</h1>
							
							<!-- Custom Alert for messages -->
							<div 
								v-if="message" 
								:class="getAlertClass()"
								class="alert-message"
							>
								{{ message }}
							</div>
							
							<v-form ref="formRef" lazy-validation @keyup.enter="loginUser" class="login-form">
								<div class="form-group">
									<label for="email" class="form-label">EMAIL</label>
									<v-text-field 
										v-model="email" 
										:rules="rules.email"
										type="email" 
										required
										variant="outlined"
										density="comfortable"
										placeholder="Enter your email"
										hide-details="auto"
										class="large-input"
									></v-text-field>
								</div>
								
								<div class="form-group">
									<label for="password" class="form-label">PASSWORD</label>
									<v-text-field 
										v-model="password" 
										:rules="rules.password"
										:type="showPassword ? 'text' : 'password'"
										required
										variant="outlined"
										density="comfortable"
										placeholder="Enter your password"
										hide-details="auto"
										class="large-input"
										:append-inner-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
										@click:append-inner="showPassword = !showPassword"
									></v-text-field>
								</div>
								
								<v-btn 
									@click="loginUser"
									:loading="isSubmitting"
									:disabled="isSubmitting"
									class="btn-brown large-btn"
									block
								>
									<span v-if="isSubmitting">Signing In...</span>
									<span v-else>Continue ></span>
								</v-btn>
							</v-form>
						</div>
					</div>
					
					<!-- Right side - Welcome/Register Section -->
					<div class="welcome-section">
						<div class="welcome-content">
							<h1 class="welcome-title">Join Our Community</h1>
							<p class="welcome-text">Don't have an account?</p>
							<v-btn 
								@click="redirectToRegister"
								class="btn-brown large-btn signup-btn"
							>
								Sign Up
							</v-btn>
						</div>
					</div>
				</div>
			</v-main>
		</v-app>
	`
}).use(vuetify).mount("#app");