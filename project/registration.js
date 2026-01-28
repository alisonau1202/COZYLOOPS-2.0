const { createApp, ref } = Vue;
const { createVuetify } = Vuetify;

const vuetify = createVuetify();

createApp({
	setup() {
		const name = ref('');
		const email = ref('');
		const password = ref('');
		const confirmPassword = ref('');
		const message = ref('');
		const isSuccess = ref(false);
		const isError = ref(false);
		const isWelcome = ref(false);
		const formRef = ref(null);
		const isSubmitting = ref(false);
		const showPassword = ref(false); // New ref for password visibility
		const showConfirmPassword = ref(false); // New ref for confirm password visibility

		const rules = {
			name: [
				(v) => !!v || 'Name is required',
				(v) => /^[A-Za-z\s]+$/.test(v) || 'Only letters allowed'
			],

			email: [
				(v) => !!v || 'Email is required',
				(v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Invalid email'
			],

			password: [
				(v) => !!v || 'Password is required',
				(v) => v.length >= 8 || 'Password must be at least 8 characters',
				(v) => /[A-Z]/.test(v) || 'Password must contain at least one uppercase letter',
				(v) => /[0-9]/.test(v) || 'Password must contain at least one number',
				(v) => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(v) || 'Password must contain at least one special character',
			],

			confirmPassword: [
				(v) => !!v || 'Confirm Password is required',
				(v) => v === password.value || 'Passwords do not match'
			]
		};

		const clearForm = () => {
			name.value = '';
			email.value = '';
			password.value = '';
			confirmPassword.value = '';
		};

		const redirectToLogin = () => {
			window.location.href = 'login.php';
		};

		const registerUser = async () => {
			const { valid } = await formRef.value.validate();
			if (!valid) {
				isError.value = true;
				isSuccess.value = false;
				isWelcome.value = false;
				message.value = "Please correct all form errors before submitting.";
				// Add shake animation to form
				const registrationContainer = document.querySelector('.login-container');
				if (registrationContainer) {
					registrationContainer.classList.add('error-shake');
					setTimeout(() => {
						registrationContainer.classList.remove('error-shake');
					}, 500);
				}
				return;
			}

			isSuccess.value = false;
			isError.value = false;
			isWelcome.value = false;
			message.value = '';

			try {
				isSubmitting.value = true;
				const response = await fetch("registration.php", {
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({
						name: name.value,
						email: email.value,
						password: password.value
					})
				});

				const result = await response.json();

				if (response.ok) {
					message.value = result.message;
					isSuccess.value = true;

					// After 1 second, replace with welcome message and change styling
					setTimeout(() => {
						message.value = `Welcome, ${name.value}!`;
						// Change to welcome styling
						isSuccess.value = false;
						isError.value = false;
						isWelcome.value = true;

						clearForm();
						formRef.value.resetValidation();

						// After another 2 seconds, redirect
						setTimeout(() => {
							if (result.redirectUrl) {
								window.location.href = result.redirectUrl;
							} else {
								window.location.href = 'login.php';
							}
						}, 2000);
					}, 1000);

				} else {
					message.value = result.message || "Registration failed.";
					isError.value = true;
					isSuccess.value = false;
					isWelcome.value = false;

					// Add shake animation to form on error
					const registrationContainer = document.querySelector('.login-container');
					if (registrationContainer) {
						registrationContainer.classList.add('error-shake');
						setTimeout(() => {
							registrationContainer.classList.remove('error-shake');
						}, 500);
					}
				}
			} catch (error) {
				message.value = "Network error: " + error.message;
				isError.value = true;
				isSuccess.value = false;
				isWelcome.value = false;

				// Add shake animation to form on network error
				const registrationContainer = document.querySelector('.login-container');
				if (registrationContainer) {
					registrationContainer.classList.add('error-shake');
					setTimeout(() => {
						registrationContainer.classList.remove('error-shake');
					}, 500);
				}
			} finally {
				isSubmitting.value = false;
			}
		};

		// Function to get custom alert class based on message type
		const getAlertClass = () => {
			if (isError.value) return 'login-error-message';
			if (isSuccess.value) return 'login-success-message';
			if (isWelcome.value) return 'welcome-message-elegant';
			return '';
		};

		return {
			name,
			email,
			password,
			confirmPassword,
			message,
			isSuccess,
			isError,
			isWelcome,
			registerUser,
			redirectToLogin,
			rules,
			formRef,
			isSubmitting,
			getAlertClass,
			showPassword, // Expose new ref
			showConfirmPassword // Expose new ref
		};
	},
	template: `
		<v-app>
			<v-main style="padding: 0 !important;">
				<div class="login-wrapper">
					<!-- Left side - Registration Form -->
					<div class="login-form-section">
						<div class="login-container">
							<h1 class="login-title">Registration</h1>

							<!-- Custom Alert for messages -->
							<div 
								v-if="message" 
								:class="getAlertClass()"
								class="alert-message"
							>
								{{ message }}
							</div>

							<v-form ref="formRef" lazy-validation @keyup.enter="registerUser" class="login-form">
								<div class="form-group">
									<label for="name" class="form-label">NAME</label>
									<v-text-field 
										v-model="name" 
										:rules="rules.name"
										required
										variant="outlined"
										density="comfortable"
										placeholder="Enter your full name"
										hide-details="auto"
										class="large-input"
									></v-text-field>
								</div>

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

								<div class="form-group">
									<label for="confirmPassword" class="form-label">CONFIRM PASSWORD</label>
									<v-text-field 
										v-model="confirmPassword" 
										:rules="rules.confirmPassword"
										:type="showConfirmPassword ? 'text' : 'password'"
										required
										variant="outlined"
										density="comfortable"
										placeholder="Confirm your password"
										hide-details="auto"
										class="large-input"
										:disabled="!password"
										:append-inner-icon="showConfirmPassword ? 'mdi-eye' : 'mdi-eye-off'"
										@click:append-inner="showConfirmPassword = !showConfirmPassword"
									></v-text-field>
								</div>

								<v-btn 
									@click="registerUser"
									:loading="isSubmitting"
									:disabled="isSubmitting"
									class="btn-brown large-btn"
									block
								>
									<span v-if="isSubmitting">Creating Account...</span>
									<span v-else>Continue ></span>
								</v-btn>
							</v-form>
						</div>
					</div>

					<!-- Right side - Welcome/Login Section -->
					<div class="welcome-section">
						<div class="welcome-content">
							<h1 class="welcome-title">Welcome to Registration</h1>
							<p class="welcome-text">Already have an account?</p>
							<v-btn 
								@click="redirectToLogin"
								class="btn-brown large-btn signup-btn"
							>
								Sign In
							</v-btn>
						</div>
					</div>
				</div>
			</v-main>
		</v-app>
	`
}).use(vuetify).mount("#app");