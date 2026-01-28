const { createApp, ref, reactive, onMounted, nextTick } = Vue;
const { createVuetify } = Vuetify;

const vuetify = createVuetify();

createApp({
	setup() {
		const user = reactive({ ...window.currentUser });
		const userForm = reactive({
            ...user,
            password: '', // Ensure password is empty initially for security
            password_confirm: '' // New field for password confirmation
        });

		// Form refs for Vuetify validation
		const userFormRef = ref(null);
		const addressFormRef = ref(null);

		// Controls dropdown visibility for profile editing
		const showEditUser = ref(false);

        // New refs for password visibility toggles
        const showPassword = ref(false);
        const showConfirmPassword = ref(false);

		// Address management state
		const addresses = ref([]);
		const showAddressForm = ref(false);
		const editingAddress = ref(null);
		const addressForm = reactive({
			name: '',
			phone: '',
			country_code: '+60', // Default to Malaysia
			unit_number: '',
			street: '',
			city: '',
			state: '',
			postcode: '',
			country: 'Malaysia' // Default to Malaysia
		});

		// Confirmation modal state
		const confirmTitle = ref('');
		const confirmMessage = ref('');
		const confirmAction = ref(null);

		// Country codes data
		const countryCodes = [
			{ code: '+60', country: 'Malaysia', flag: '🇲🇾' },
			{ code: '+65', country: 'Singapore', flag: '🇸🇬' },
			{ code: '+62', country: 'Indonesia', flag: '🇮🇩' },
			{ code: '+66', country: 'Thailand', flag: '🇹🇭' },
			{ code: '+84', country: 'Vietnam', flag: '🇻🇳' },
			{ code: '+63', country: 'Philippines', flag: '🇵🇭' },
			{ code: '+673', country: 'Brunei', flag: '🇧🇳' },
			{ code: '+95', country: 'Myanmar', flag: '🇲🇲' },
			{ code: '+855', country: 'Cambodia', flag: '🇰🇭' },
			{ code: '+856', country: 'Laos', flag: '🇱🇦' },
			{ code: '+1', country: 'USA/Canada', flag: '🇺🇸' },
			{ code: '+44', country: 'United Kingdom', flag: '🇬🇧' },
			{ code: '+61', country: 'Australia', flag: '🇦🇺' },
			{ code: '+86', country: 'China', flag: '🇨🇳' },
			{ code: '+91', country: 'India', flag: '�🇳' },
			{ code: '+81', country: 'Japan', flag: '🇯🇵' },
			{ code: '+82', country: 'South Korea', flag: '🇰🇷' }
		];

		// Countries list
		const countries = [
			'Malaysia', 'Singapore', 'Indonesia', 'Thailand', 'Vietnam', 'Philippines', 
			'Brunei', 'Myanmar', 'Cambodia', 'Laos', 'United States', 'Canada', 
			'United Kingdom', 'Australia', 'China', 'India', 'Japan', 'South Korea',
			'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Belgium', 'Switzerland',
			'Sweden', 'Norway', 'Denmark', 'Finland', 'New Zealand', 'Brazil', 'Argentina',
			'Chile', 'Mexico', 'South Africa', 'Egypt', 'UAE', 'Saudi Arabia', 'Turkey'
		];

		// Malaysian states
		const malaysianStates = [
			'Johor', 'Kedah', 'Kelantan', 'Malacca', 'Negeri Sembilan', 'Pahang',
			'Penang', 'Perak', 'Perlis', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu',
			'Federal Territory of Kuala Lumpur', 'Federal Territory of Labuan', 'Federal Territory of Putrajaya'
		];

		// Other states/provinces by country
		const statesByCountry = {
			'Malaysia': malaysianStates,
			'Singapore': ['Central Region', 'East Region', 'North Region', 'North-East Region', 'West Region'],
			'Indonesia': ['Jakarta', 'West Java', 'Central Java', 'East Java', 'Bali', 'Sumatra', 'Kalimantan', 'Sulawesi'],
			'Thailand': ['Bangkok', 'Chiang Mai', 'Phuket', 'Pattaya', 'Krabi', 'Samui', 'Hua Hin'],
			'Philippines': ['Metro Manila', 'Cebu', 'Davao', 'Iloilo', 'Cagayan de Oro', 'Zamboanga'],
			'United States': ['Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia'],
			'Canada': ['Ontario', 'Quebec', 'British Columbia', 'Alberta', 'Manitoba', 'Saskatchewan', 'Nova Scotia', 'New Brunswick'],
			'Australia': ['New South Wales', 'Victoria', 'Queensland', 'Western Australia', 'South Australia', 'Tasmania', 'Northern Territory', 'Australian Capital Territory']
		};

		// Get states for selected country
		const getStatesForCountry = (country) => {
			return statesByCountry[country] || [];
		};

		// Watch for country changes to update state options
		const onCountryChange = () => {
			const states = getStatesForCountry(addressForm.country);
			if (states.length > 0 && !states.includes(addressForm.state)) {
				addressForm.state = '';
			}
		};

		// ============ USER PROFILE VALIDATION ============

		// Rules for Vuetify's validation system
		const userRules = {
			name: [
				v => !!v.trim() || 'Name is required',
				v => v.trim().length >= 2 || 'Name must be at least 2 characters',
				v => /^[a-zA-Z\s]+$/.test(v.trim()) || 'Name must contain only alphabetic characters'
			],
			email: [
				v => !!v.trim() || 'Email is required',
				v => /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(v.trim()) || 'Please enter a valid email address'
			],
			password: [
				// Password is optional on profile update, but if entered, must meet criteria
				v => !v || v.length >= 8 || 'Password must be at least 8 characters',
				v => !v || /[A-Z]/.test(v) || 'Password must contain at least one uppercase letter',
				v => !v || /[0-9]/.test(v) || 'Password must contain at least one number',
				v => !v || /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(v) || 'Password must contain at least one special character'
			],
            password_confirm: [
                v => {
                    // Only require confirmation if a new password is being set
                    if (userForm.password) {
                        return !!v || 'Password confirmation is required';
                    }
                    return true; // Not required if password field is empty
                },
                v => {
                    // Only validate match if a new password is being set
                    if (userForm.password) {
                        return v === userForm.password || 'Passwords do not match';
                    }
                    return true; // No need to match if password field is empty
                }
            ]
		};

		// ============ ADDRESS VALIDATION ============

		const addressRules = {
			name: [
				v => !!v.trim() || 'Name is required',
				v => v.trim().length >= 2 || 'Name must be at least 2 characters'
			],
			phone: [
				v => !!v.trim() || 'Phone number is required',
				v => /^[\d\s\-\+\(\)]+$/.test(v.trim()) || 'Please enter a valid phone number',
				v => v.trim().length >= 7 || 'Phone number must be at least 7 digits',
				v => v.trim().length <= 15 || 'Phone number must not exceed 15 digits'
			],
			street: [
				v => !!v.trim() || 'Street address is required',
				v => v.trim().length >= 5 || 'Please enter a complete street address'
			],
			city: [
				v => !!v.trim() || 'City is required',
				v => v.trim().length >= 2 || 'City must be at least 2 characters'
			],
			state: [
				v => !!v.trim() || 'State/Province is required'
			],
			postcode: [
				v => !!v.trim() || 'Postcode is required',
				v => {
					const code = v.trim();
					// Malaysian postcode validation
					if (addressForm.country === 'Malaysia') {
						return /^\d{5}$/.test(code) || 'Malaysian postcode must be 5 digits';
					}
					// Generic postcode validation for other countries
					return /^[A-Za-z0-9\s\-]{3,10}$/.test(code) || 'Please enter a valid postcode';
				}
			],
			country: [
				v => !!v.trim() || 'Country is required'
			]
		};

		// ============ CONFIRMATION MODAL ============

		// Show confirmation dialog
		const showConfirm = (title, message, action) => {
			confirmTitle.value = title;
			confirmMessage.value = message;
			confirmAction.value = action;
			
			// Use Bootstrap modal
			const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
			modal.show();
		};

		// Execute the confirmed action
		const executeConfirmAction = () => {
			if (confirmAction.value) {
				confirmAction.value();
			}
			closeConfirm();
		};

		// Close confirmation modal
		const closeConfirm = () => {
			const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
			if (modal) {
				modal.hide();
			}
			confirmAction.value = null;
		};

		// ============ USER PROFILE FUNCTIONS ============

		// Actual save user function (called after confirmation)
		const performSaveUser = async () => {
			try {
                // Only include password and password_confirm if password field is not empty
                const payloadUser = {
                    name: userForm.name,
                    email: userForm.email
                };

                if (userForm.password) {
                    payloadUser.password = userForm.password;
                    payloadUser.password_confirm = userForm.password_confirm;
                }

				const payload = {
					action: 'update_user',
					user: payloadUser
				};

				const res = await fetch('profile_api.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(payload)
				});

				const data = await res.json();

				if (data.success) {
					Object.assign(user, {
						name: userForm.name,
						email: userForm.email
					});

					userForm.password = '';
                    userForm.password_confirm = ''; // Clear confirmation field
					showEditUser.value = false;

					nextTick(() => {
						userFormRef.value?.resetValidation();
					});

					showConfirm('Success!', 'Your profile has been updated successfully!', null);
				} else {
					showConfirm('Error', data.error || "Failed to update profile. Please try again.", null);
				}
			} catch (err) {
				console.error("Error saving user info:", err);
				showConfirm('Error', 'An error occurred while saving your profile. Please try again.', null);
			}
		};

		// Save user with validation and confirmation
		const saveUser = async () => {
			const { valid } = await userFormRef.value.validate();
			if (!valid) return;

			showConfirm(
				'Confirm Changes',
				'Are you sure you want to save these changes to your profile?',
				performSaveUser
			);
		};

		// Cancel editing and reset form
		const cancelEdit = () => {
			Object.assign(userForm, user);
			userForm.password = '';
            userForm.password_confirm = ''; // Clear confirmation field
			showEditUser.value = false;
			
			nextTick(() => {
				userFormRef.value?.resetValidation();
			});
		};

		// ============ ADDRESS MANAGEMENT FUNCTIONS ============

		// Load addresses from server
		const loadAddresses = async () => {
			try {
				const res = await fetch('profile_api.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ action: 'get_addresses' })
				});

				const data = await res.json();
				if (data.success) {
					addresses.value = data.addresses || [];
				} else {
					console.error('Failed to load addresses:', data.error);
				}
			} catch (err) {
				console.error('Error loading addresses:', err);
			}
		};

		// Reset address form
		const resetAddressForm = () => {
			Object.assign(addressForm, {
				name: '',
				phone: '',
				country_code: '+60',
				unit_number: '',
				street: '',
				city: '',
				state: '',
				postcode: '',
				country: 'Malaysia'
			});
			editingAddress.value = null;
		};

		// Show add address form
		const showAddAddress = () => {
			resetAddressForm();
			showAddressForm.value = true;
		};

		// Show edit address form
		const showEditAddress = (address) => {
			// Parse the phone number to separate country code and number
			let countryCode = '+60';
			let phoneNumber = address.phone;
			
			// Try to extract country code from phone number
			for (const cc of countryCodes) {
				if (address.phone.startsWith(cc.code)) {
					countryCode = cc.code;
					phoneNumber = address.phone.substring(cc.code.length).trim();
					break;
				}
			}
			
			Object.assign(addressForm, {
				...address,
				phone: phoneNumber,
				country_code: countryCode,
				state: address.state || '',
				country: address.country || 'Malaysia'
			});
			editingAddress.value = address.id;
			showAddressForm.value = true;
		};

		// Cancel address form
		const cancelAddressForm = () => {
			resetAddressForm();
			showAddressForm.value = false;
			
			nextTick(() => {
				addressFormRef.value?.resetValidation();
			});
		};

		// Save address (create or update)
		const saveAddress = async () => {
			const { valid } = await addressFormRef.value.validate();
			if (!valid) return;

			try {
				// Combine country code and phone number
				const fullPhoneNumber = addressForm.country_code + addressForm.phone;
				
				const payload = {
					action: editingAddress.value ? 'update_address' : 'create_address',
					address: { 
						...addressForm,
						phone: fullPhoneNumber // Send combined phone number
					}
				};

				if (editingAddress.value) {
					payload.address.id = editingAddress.value;
				}

				const res = await fetch('profile_api.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(payload)
				});

				const data = await res.json();

				if (data.success) {
					await loadAddresses(); // Reload addresses
					cancelAddressForm();
					showConfirm(
						'Success!', 
						editingAddress.value ? 'Address updated successfully!' : 'Address added successfully!', 
						null
					);
				} else {
					showConfirm('Error', data.error || 'Failed to save address. Please try again.', null);
				}
			} catch (err) {
				console.error('Error saving address:', err);
				showConfirm('Error', 'An error occurred while saving the address. Please try again.', null);
			}
		};

		// Delete address with confirmation
		const confirmDeleteAddress = (address) => {
			showConfirm(
				'Delete Address',
				`Are you sure you want to delete the address for ${address.name}?`,
				() => deleteAddress(address.id)
			);
		};

		// Actually delete the address
		const deleteAddress = async (addressId) => {
			try {
				const res = await fetch('profile_api.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ 
						action: 'delete_address',
						address_id: addressId
					})
				});

				const data = await res.json();

				if (data.success) {
					await loadAddresses(); // Reload addresses
					showConfirm('Success!', 'Address deleted successfully!', null);
				} else {
					showConfirm('Error', data.error || 'Failed to delete address. Please try again.', null);
				}
			} catch (err) {
				console.error('Error deleting address:', err);
				showConfirm('Error', 'An error occurred while deleting the address. Please try again.', null);
			}
		};

		onMounted(() => {
			console.log("Component mounted, current user:", user);
			loadAddresses();
		});

		return {
			user,
			userForm,
			userFormRef,
			userRules,
			saveUser,
			cancelEdit,
			showEditUser,
            showPassword, // Expose new ref
            showConfirmPassword, // Expose new ref
			// Address management
			addresses,
			addressForm,
			addressFormRef,
			addressRules,
			showAddressForm,
			editingAddress,
			showAddAddress,
			showEditAddress,
			saveAddress,
			cancelAddressForm,
			confirmDeleteAddress,
			// New data
			countryCodes,
			countries,
			getStatesForCountry,
			onCountryChange,
			// Confirmation modal
			confirmTitle,
			confirmMessage,
			executeConfirmAction,
			closeConfirm
		};
	},

	template: `
		<v-app>
			<v-main class="profile-main-content">
				<v-container class="mt-6" style="max-width: 1000px;">
					<!-- User Profile Section -->
					<v-card class="pa-6 custom-card mb-6">
						<div class="d-flex justify-space-between align-center mb-4">
							<h2 class="section-title">User Profile</h2>
							<v-btn 
								v-if="!showEditUser" 
								class="profile-btn btn-brown" 
								prepend-icon="mdi-square-edit-outline"
								@click="showEditUser = true"
							>
								Edit Info
							</v-btn>
						</div>

						<div v-if="!showEditUser" class="user-info-display">
							<p><strong>Name:</strong> {{ user.name }}</p>
							<p><strong>Email:</strong> {{ user.email }}</p>
						</div>

						<div v-if="showEditUser" class="mt-4 profile-form">
							<h4 class="mb-4">Edit Profile Information</h4>
							<v-form ref="userFormRef" lazy-validation>
								<v-text-field
									label="Name"
									v-model="userForm.name"
									:rules="userRules.name"
									variant="outlined"
									class="mb-3"
								></v-text-field>
								<v-text-field
									label="Email"
									v-model="userForm.email"
									:rules="userRules.email"
									variant="outlined"
									class="mb-3"
								></v-text-field>
								<v-text-field
									label="New Password (leave empty to keep current)"
									v-model="userForm.password"
									:rules="userRules.password"
									:type="showPassword ? 'text' : 'password'"
									variant="outlined"
									class="mb-3"
									hint="Leave empty if you don't want to change your password"
									persistent-hint
                                    :append-inner-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                                    @click:append-inner="showPassword = !showPassword"
								></v-text-field>
                                <v-text-field
									label="Confirm New Password"
									v-model="userForm.password_confirm"
									:rules="userRules.password_confirm"
									:type="showConfirmPassword ? 'text' : 'password'"
									variant="outlined"
									class="mb-4"
                                    :disabled="!userForm.password"
                                    :append-inner-icon="showConfirmPassword ? 'mdi-eye' : 'mdi-eye-off'"
                                    @click:append-inner="showConfirmPassword = !showConfirmPassword"
								></v-text-field>
								<div class="d-flex gap-2">
									<v-btn class="profile-btn success-btn" 
									prepend-icon="mdi-content-save" 
									@click="saveUser">
										Save Changes
									</v-btn>
									<v-btn class="profile-btn cancel-btn" @click="cancelEdit">
										Cancel
									</v-btn>
								</div>
							</v-form>
						</div>
					</v-card>

					<!-- Shipping Addresses Section -->
					<v-card class="pa-6 custom-card">
						<div class="d-flex justify-space-between align-center mb-4">
							<h2 class="section-title">Shipping Addresses</h2>
							<v-btn 
								v-if="!showAddressForm" 
								class="profile-btn btn-brown" 
								@click="showAddAddress"
								prepend-icon="mdi-plus"
							>
								Add Address
							</v-btn>
						</div>

						<!-- Address Form -->
						<div v-if="showAddressForm" class="mb-6">
							<h4 class="mb-4">{{ editingAddress ? 'Edit Address' : 'Add New Address' }}</h4>
							<v-form ref="addressFormRef" lazy-validation>
								<v-row>
									<v-col cols="12" md="6">
										<v-text-field
											label="Full Name"
											v-model="addressForm.name"
											:rules="addressRules.name"
											variant="outlined"
										></v-text-field>
									</v-col>
									<v-col cols="12" md="6">
										<div class="d-flex gap-2">
											<v-select
												label="Country Code"
												v-model="addressForm.country_code"
												:items="countryCodes"
												item-title="country"
												item-value="code"
												variant="outlined"
												style="flex: 0 0 140px; max-height: 10px;"
												:menu-props="{ maxHeight: 300 }"
											>
												<template v-slot:selection="{ item }">
													<span>{{ item.raw.flag }} {{ item.raw.code }}</span>
												</template>
												<template v-slot:item="{ item, props }">
													<v-list-item v-bind="props">
														<template v-slot:prepend>
															<span class="me-2">{{ item.raw.flag }}</span>
														</template>
														<v-list-item-title>{{ item.raw.code }} - {{ item.raw.country }}</v-list-item-title>
													</v-list-item>
												</template>
											</v-select>
											<v-text-field
												label="Phone Number"
												v-model="addressForm.phone"
												:rules="addressRules.phone"
												variant="outlined"
												style="flex: 1;"
											></v-text-field>
										</div>
									</v-col>
								</v-row>
								<v-row>
									<v-col cols="12" md="4">
										<v-text-field
											label="Unit Number (Optional)"
											v-model="addressForm.unit_number"
											variant="outlined"
											hint="House, Apartment, suite, unit, etc."
											persistent-hint
										></v-text-field>
									</v-col>
									<v-col cols="12" md="8">
										<v-text-field
											label="Street Address"
											v-model="addressForm.street"
											:rules="addressRules.street"
											variant="outlined"
										></v-text-field>
									</v-col>
								</v-row>
								<v-row>
									<v-col cols="12" md="4">
										<v-text-field
											label="City"
											v-model="addressForm.city"
											:rules="addressRules.city"
											variant="outlined"
										></v-text-field>
									</v-col>
									<v-col cols="12" md="8">
										<v-text-field
											label="Postcode"
											v-model="addressForm.postcode"
											:rules="addressRules.postcode"
											variant="outlined"
										></v-text-field>
									</v-col>
								</v-row>
								<v-row>
									<v-col cols="12" md="4">
										<v-select
											label="State/Province"
											v-model="addressForm.state"
											:items="getStatesForCountry(addressForm.country)"
											:rules="addressRules.state"
											variant="outlined"
											:no-data-text="'No states available for ' + addressForm.country"
											:menu-props="{ maxHeight: 300 }"
										></v-select>
									</v-col>
									<v-col cols="12" md="8">
										<v-select
											label="Country"
											v-model="addressForm.country"
											:items="countries"
											:rules="addressRules.country"
											variant="outlined"
											@update:modelValue="onCountryChange"
											:menu-props="{ maxHeight: 300 }"
										></v-select>
									</v-col>
								</v-row>
								<div class="d-flex gap-2 mt-4">
									<v-btn class="profile-btn success-btn" 
									prepend-icon="mdi-content-save" 
									@click="saveAddress">
										{{ editingAddress ? 'Update Address' : 'Save Address' }}
									</v-btn>
									<v-btn class="profile-btn cancel-btn" @click="cancelAddressForm">
										Cancel
									</v-btn>
								</div>
							</v-form>
						</div>

						<!-- Address List -->
						<div v-if="!showAddressForm">
							<div v-if="addresses.length === 0" class="text-center text-muted py-8">
								<v-icon size="64" class="mb-4">mdi-map-marker-outline</v-icon>
								<p>No shipping addresses found. Add your first address to get started!</p>
							</div>

							<v-row v-else>
								<v-col v-for="address in addresses" :key="address.id" cols="12" md="6" lg="4">
									<v-card class="address-card pa-4" elevation="2">
										<div class="address-header d-flex justify-space-between align-start mb-3">
											<h5>{{ address.name }}</h5>
											<div class="address-actions">
												<v-btn 
													icon 
													size="small" 
													variant="text" 
													@click="showEditAddress(address)"
													class="me-1"
												>
													<v-icon size="20">mdi-pencil</v-icon>
												</v-btn>
												<v-btn 
													icon 
													size="small" 
													variant="text" 
													@click="confirmDeleteAddress(address)"
												>
													<v-icon size="20">mdi-delete</v-icon>
												</v-btn>
											</div>
										</div>
										<div class="address-details text-muted">
											<p class="mb-1"><v-icon size="20" class="me-2">mdi-phone</v-icon>{{ address.phone }}</p>
											<p class="mb-1">
												<v-icon size="20" class="me-2">mdi-map-marker</v-icon>
												<span v-if="address.unit_number">{{ address.unit_number }}, </span>{{ address.street }}
											</p>
											<p class="mb-1">{{ address.city }}, {{ address.state }} {{ address.postcode }}</p>
											<p class="mb-0"><strong>{{ address.country || 'Malaysia' }}</strong></p>
										</div>
									</v-card>
								</v-col>
							</v-row>
						</div>
					</v-card>
				</v-container>
			</v-main>

			<!-- Confirmation Modal -->
			<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content custom-card">
						<div class="modal-header">
							<h5 class="modal-title section-title" id="confirmationModalLabel">{{ confirmTitle }}</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" @click="closeConfirm"></button>
						</div>
						<div class="modal-body text-muted">
							{{ confirmMessage }}
						</div>
						<div class="modal-footer">
							<button type="button" class="btn profile-btn cancel-btn" data-bs-dismiss="modal" @click="closeConfirm">
								{{ confirmTitle === 'Success!' || confirmTitle === 'Error' ? 'OK' : 'Cancel' }}
							</button>
							<button 
								v-if="confirmTitle !== 'Success!' && confirmTitle !== 'Error'" 
								type="button" 
								class="btn profile-btn success-btn" 
								@click="executeConfirmAction"
							>
								Confirm
							</button>
						</div>
					</div>
				</div>
			</div>
		</v-app>
	`
}).use(vuetify).mount('#profile-app');