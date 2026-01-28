const { createApp } = Vue;

createApp({
	data() {
		return {
			purchases: [],
			loading: false,
			editingOrder: null,
			searchQuery: '',
			statusFilter: '',
			sortBy: 'newest',
			confirmDialog: false, // Controls visibility of the custom confirmation modal
			confirmTitle: '',     // Title for the confirmation modal
			confirmMessage: '',   // Message for the confirmation modal
			confirmAction: null,  // Callback function to execute on confirmation
		};
	},

	computed: {
		filteredPurchases() {
			let filtered = this.purchases;

			// Filter by search query
			if (this.searchQuery) {
				const query = this.searchQuery.toLowerCase();
				filtered = filtered.filter(purchase => 
					purchase.order_number.toLowerCase().includes(query) ||
					purchase.items.some(item => item.name.toLowerCase().includes(query))
				);
			}

			// Filter by status
			if (this.statusFilter) {
				filtered = filtered.filter(purchase => purchase.order_status === this.statusFilter);
			}

			// Sort results
			return filtered.sort((a, b) => {
				switch (this.sortBy) {
					case 'oldest':
						return new Date(a.order_date) - new Date(b.order_date);
					case 'amount_high':
						return b.total_amount - a.total_amount;
					case 'amount_low':
						return a.total_amount - b.total_amount;
					default: // newest
						return new Date(b.order_date) - new Date(a.order_date);
				}
			});
		}
	},

	watch: {
		// Watch for changes in filter states and save to sessionStorage
		searchQuery(newValue) {
			this.saveFilterState();
		},
		statusFilter(newValue) {
			this.saveFilterState();
		},
		sortBy(newValue) {
			this.saveFilterState();
		}
	},

	methods: {
		// Save current filter state to sessionStorage
		saveFilterState() {
			const filterState = {
				searchQuery: this.searchQuery,
				statusFilter: this.statusFilter,
				sortBy: this.sortBy
			};
			try {
				sessionStorage.setItem('purchaseFilters', JSON.stringify(filterState));
			} catch (e) {
				// Handle cases where sessionStorage might not be available
				console.warn('Could not save filter state:', e);
			}
		},

		// Load filter state from sessionStorage
		loadFilterState() {
			try {
				const savedState = sessionStorage.getItem('purchaseFilters');
				if (savedState) {
					const filterState = JSON.parse(savedState);
					this.searchQuery = filterState.searchQuery || '';
					this.statusFilter = filterState.statusFilter || '';
					this.sortBy = filterState.sortBy || 'newest';
				}
			} catch (e) {
				// Handle cases where sessionStorage might not be available or data is corrupted
				console.warn('Could not load filter state:', e);
			}
		},

		// Clear saved filter state
		clearFilterState() {
			try {
				sessionStorage.removeItem('purchaseFilters');
			} catch (e) {
				console.warn('Could not clear filter state:', e);
			}
		},

		loadPurchases() {
			this.loading = true;
			// Return the promise from the fetch chain to allow chaining in removeItemFromOrder
			return fetch('purchase_api.php')
				.then(res => {
					if (!res.ok) {
						throw new Error(`HTTP error! status: ${res.status}`);
					}
					return res.json();
				})
				.then(data => {
					this.purchases = data.map(purchase => ({
						...purchase,
						items: purchase.items.map(item => ({
							...item,
							newQuantity: item.quantity
						}))
					}));
					this.loading = false;
					return data; // Important: return data for subsequent .then() calls
				})
				.catch(err => {
					console.error('Failed to load purchases:', err);
					this.showInfoMessage('Error', 'Failed to load purchase history. Please refresh the page.');
					this.loading = false;
					throw err; // Re-throw to propagate the error
				});
		},

		toggleEdit(orderId) {
			this.editingOrder = this.editingOrder === orderId ? null : orderId;
		},

		cancelEdit() {
			this.editingOrder = null;
			// Reset quantities to original values
			this.purchases.forEach(purchase => {
				purchase.items.forEach(item => {
					item.newQuantity = item.quantity;
				});
			});
		},

		saveOrderChanges(orderId) {
			const purchase = this.purchases.find(p => p.id === orderId);
			if (!purchase) return;

			const updates = purchase.items.map(item => ({
				id: item.id,
				quantity: item.newQuantity
			}));

			// Changed method to POST and adjusted body to send 'action: update_items_in_order'
			fetch('purchase_api.php', {
				method: 'POST', // Changed to POST
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					action: 'update_items_in_order', // New action name
					purchase_id: orderId,
					items: updates
				})
			})
				.then(res => {
					if (!res.ok) {
						throw new Error(`HTTP error! status: ${res.status}`);
					}
					return res.json();
				})
				.then((data) => {
					if (data.success) {
						this.editingOrder = null;
						this.loadPurchases();
						this.showInfoMessage('Success', data.message || 'Order updated successfully!');
					} else {
						this.showInfoMessage('Error', data.error || 'Failed to update order. Please try again.');
					}
				})
				.catch(err => {
					console.error('Failed to update order:', err);
					this.showInfoMessage('Error', 'An unexpected error occurred while updating order. Please try again.');
				});
		},

		removeItemFromOrder(purchaseId, itemId) {
			this.showConfirm(
				'Remove Item',
				'Are you sure you want to remove this item from the order?',
				() => {
					// Changed method to POST and adjusted body
					fetch('purchase_api.php', {
						method: 'POST', // Changed to POST
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({
							action: 'remove_item_from_order', // New action name
							item_id: itemId,
							purchase_id: purchaseId // Added purchase_id for backend context
						})
					})
						.then(res => {
							if (!res.ok) {
								throw new Error(`HTTP error! status: ${res.status}`);
							}
							return res.json();
						})
						.then((data) => {
							if (data.success) {
								this.showInfoMessage('Success', data.message || 'Item removed successfully!');
								// After successful item removal, reload purchases.
								// Then, check the state of the purchase and exit edit mode if it's empty.
								this.loadPurchases().then(() => {
									const updatedPurchase = this.purchases.find(p => p.id === purchaseId);
									// If the purchase no longer has items (and implicitly cancelled by backend)
									if (updatedPurchase && updatedPurchase.items.length === 0) {
										this.editingOrder = null; // Exit edit mode
									}
								});
							} else {
								this.showInfoMessage('Error', data.error || 'Failed to remove item. Please try again.');
							}
						})
						.catch(err => {
							console.error('Failed to remove item:', err);
							this.showInfoMessage('Error', 'An unexpected error occurred while removing item. Please try again.'); // Using custom message
						});
				}
			);
		},

		cancelOrder(orderId) {
			this.showConfirm(
				'Cancel Order',
				'Are you sure you want to cancel this order?',
				() => {
					// Changed method to POST and adjusted body
					fetch('purchase_api.php', {
						method: 'POST', // Changed to POST
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({
							action: 'cancel_purchase', // New action name
							purchase_id: orderId
						})
					})
						.then(res => {
							if (!res.ok) {
								throw new Error(`HTTP error! status: ${res.status}`);
							}
							return res.json();
						})
						.then((data) => {
							if (data.success) {
								this.showInfoMessage('Success', data.message || 'Order cancelled successfully.');
								this.loadPurchases(); // Reload purchases to reflect status change
							} else {
								this.showInfoMessage('Error', data.error || 'Failed to cancel order. Please try again.');
							}
						})
						.catch(err => {
							console.error('Failed to cancel order:', err);
							this.showInfoMessage('Error', 'An unexpected error occurred while cancelling order. Please try again.'); // Using custom message
						});
				}
			);
		},

		formatDate(dateString) {
			return new Date(dateString).toLocaleDateString('en-US', {
				year: 'numeric',
				month: 'long',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			});
		},

		// Custom confirmation modal functions
		showConfirm(title, message, actionCallback) {
			this.confirmTitle = title;
			this.confirmMessage = message;
			this.confirmAction = actionCallback;
			// Use Bootstrap's JavaScript API to show the modal
			const modalElement = new bootstrap.Modal(document.getElementById('confirmationModal'));
			modalElement.show();
		},

		closeConfirm() {
			this.confirmDialog = false; // Reset Vue state
			this.confirmTitle = '';
			this.confirmMessage = '';
			this.confirmAction = null;
			// Use Bootstrap's JavaScript API to hide the modal
			const modalElement = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
			if (modalElement) {
				modalElement.hide();
			}
		},

		executeConfirmAction() {
			if (this.confirmAction) {
				this.confirmAction();
			}
			this.closeConfirm(); // Close the modal after action
		},

		// Helper for displaying general info messages instead of native alerts
		showInfoMessage(title, message) {
			// You can implement a custom toast/snackbar here, or simply log to console for now
			console.log(`${title}: ${message}`);
			// For a basic visual cue without native alerts:
			// alert(`${title}: ${message}`); // Revert to custom UI later if needed
		}
	},

	mounted() {
		// Load saved filter state first, then load purchases
		this.loadFilterState();
		this.loadPurchases();
	},

	// Clean up when component is destroyed (optional)
	beforeUnmount() {
		// Optionally clear filter state when leaving the page
		// this.clearFilterState();
	}
}).mount('#purchaseApp');
