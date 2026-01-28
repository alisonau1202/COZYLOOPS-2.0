const { createApp } = Vue;
createApp({
	data() {
		return {
			rawItems: [],
			groupedItems: [],
			postageRate: 5.00, // Fixed postage rate
			freeShippingThreshold: 50.00, // Free shipping over $50
			confirmDialog: false, // Controls visibility of the custom confirmation modal
			confirmTitle: '',     // Title for the confirmation modal
			confirmMessage: '',   // Message for the confirmation modal
			confirmAction: null,  // Callback function to execute on confirmation
		};
	},
	computed: {
		subtotal() {
			return this.groupedItems
				.filter(item => item.selected)
				.reduce((sum, item) => sum + (item.price * item.quantity), 0);
		},
		postage() {
			// Free shipping if subtotal is over threshold
			if (this.subtotal >= this.freeShippingThreshold) {
				return 0;
			}
			// No postage if cart is empty
			if (this.subtotal === 0) {
				return 0;
			}
			return this.postageRate;
		},
		totalPrice() {
			return this.subtotal + this.postage;
		},
		selectedItemsCount() {
			return this.groupedItems.filter(item => item.selected).length;
		}
	},
	methods: {
		groupItems() {
			// Store current selection state before grouping
			const currentSelections = {};
			this.groupedItems.forEach(item => {
				currentSelections[item.id] = item.selected;
			});

			const grouped = {};
			this.rawItems.forEach(item => {
				if (!grouped[item.product_id]) {
					grouped[item.product_id] = {
						id: item.product_id, // This is the product_id, used for removal of all instances
						name: item.name,
						price: parseFloat(item.price),
						image: item.image,
						quantity: 1,
						// Preserve previous selection state, default to true for new items
						selected: currentSelections[item.product_id] !== undefined ? currentSelections[item.product_id] : true
					};
				} else {
					grouped[item.product_id].quantity += 1;
				}
			});
			this.groupedItems = Object.values(grouped);
		},
		reloadCart() {
			fetch('cart_api.php')
				.then(res => {
					if (!res.ok) {
						throw new Error(`HTTP error! status: ${res.status}`);
					}
					return res.json();
				})
				.then(data => {
					this.rawItems = data;
					this.groupItems();
				})
				.catch(err => {
					console.error('Failed to load cart items:', err);
					this.showInfoMessage('Error', 'Failed to load cart items. Please refresh the page.'); // Using custom message
				});
		},
		removeAll(item) {
			this.showConfirm(
				'Remove Item',
				`Are you sure you want to remove all ${item.name} from cart?`,
				() => {
					// Changed method to POST and adjusted body to send 'action: remove_all_items'
					fetch('cart_api.php', {
						method: 'POST', // Changed to POST
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ action: 'remove_all_items', id: item.id }) // Changed payload
					})
					.then(res => {
						if (!res.ok) {
							throw new Error(`HTTP error! status: ${res.status}`);
						}
						return res.json();
					})
					.then((data) => {
						if (data.success) {
							this.showInfoMessage('Success', data.message || 'Item(s) removed from cart successfully!');
							this.reloadCart();
						} else {
							this.showInfoMessage('Error', data.error || 'Failed to remove item. Please try again.');
						}
					})
					.catch(err => {
						console.error('Failed to remove item:', err);
						this.showInfoMessage('Error', 'An unexpected error occurred while removing item. Please try again.');
					});
				}
			);
		},
		removeOne(item) {
			// Changed method to POST and adjusted body to send 'action: remove_one_item'
			fetch('cart_api.php', {
				method: 'POST', // Changed to POST
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ action: 'remove_one_item', id: item.id }) // item.id here refers to product_id
			})
				.then(res => {
					if (!res.ok) {
						throw new Error(`HTTP error! status: ${res.status}`);
					}
					return res.json();
				})
				.then((data) => {
					if (data.success) {
						this.showInfoMessage('Success', data.message || 'One item removed.');
						this.reloadCart();
					} else {
						this.showInfoMessage('Error', data.error || 'Failed to remove one item. Please try again.');
					}
				})
				.catch(err => {
					console.error('Failed to remove item:', err);
					this.showInfoMessage('Error', 'Failed to update quantity. Please try again.'); // Using custom message
				});
		},
		addOne(item) {
			// Changed method to POST and adjusted body to send 'action: add_one_item'
			fetch('cart_api.php', {
				method: 'POST', // Changed to POST
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					action: 'add_one_item', // New action name
					id: item.id, // item.id here refers to product_id
					name: item.name,
					price: item.price,
					image: item.image
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
						this.showInfoMessage('Success', data.message || 'One item added.');
						this.reloadCart();
					} else {
						this.showInfoMessage('Error', data.error || 'Failed to add one item. Please try again.');
					}
				})
				.catch(err => {
					console.error('Failed to add item:', err);
					this.showInfoMessage('Error', 'Failed to update quantity. Please try again.'); // Using custom message
				});
		},
		toggleSelectAll() {
			const allSelected = this.groupedItems.every(item => item.selected);
			this.groupedItems.forEach(item => {
				item.selected = !allSelected;
			});
		},
		clearCart() {
			this.showConfirm(
				'Clear Cart',
				'Are you sure you want to clear the entire cart?',
				() => {
					// Changed method to POST and adjusted body to send 'action: clear_cart'
					fetch('cart_api.php', {
						method: 'POST', // Changed to POST
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ action: 'clear_cart' }) // Changed payload
					})
					.then(res => {
						if (!res.ok) {
							throw new Error(`HTTP error! status: ${res.status}`);
						}
						return res.json();
					})
					.then((data) => {
						if (data.success) {
							this.showInfoMessage('Success', data.message || 'Cart cleared successfully!');
							this.reloadCart();
						} else {
							this.showInfoMessage('Error', data.error || 'Failed to clear cart. Please try again.');
						}
					})
					.catch(err => {
						console.error('Failed to clear cart:', err);
						this.showInfoMessage('Error', 'An unexpected error occurred while clearing cart. Please try again.');
					});
				}
			);
		},
		async proceedToCheckout() {
			const selectedItems = this.groupedItems.filter(item => item.selected);

			if (selectedItems.length === 0) {
				this.showInfoMessage('Info', 'Please select items to checkout'); // Using custom message
				return;
			}

			// Calculate subtotal for only selected items
			const selectedSubtotal = selectedItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
			// Calculate postage for only selected items
			const selectedPostage = selectedSubtotal >= this.freeShippingThreshold ? 0 : this.postageRate;
			// Calculate total price for only selected items
			const selectedTotalPrice = selectedSubtotal + selectedPostage;


			this.showConfirm(
				'Proceed to Checkout',
				`Proceed to checkout with ${selectedItems.length} items for $${selectedTotalPrice.toFixed(2)}?`,
				async () => {
					try {
						// Show loading state
						const checkoutBtn = document.querySelector('button[data-checkout]');
						const originalContent = checkoutBtn ? checkoutBtn.innerHTML : '<i class="fas fa-credit-card"></i> Proceed to Checkout';
						if (checkoutBtn) {
							checkoutBtn.disabled = true;
							checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
						}

						const response = await fetch('purchase_api.php', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json'
							},
							body: JSON.stringify({
								action: 'create_from_cart',
								selected_items: selectedItems // Send only selected items
							})
						});

						if (!response.ok) {
							throw new Error(`HTTP error! status: ${response.status}`);
						}

						const result = await response.json();

						if (result.success) {
							this.showInfoMessage('Success', `Order created successfully! Order #${result.order_number}`); // Using custom message

							// Reload cart to show it's empty
							this.reloadCart();

							// Optionally redirect to purchase history with confirmation modal
							this.showConfirm(
								'View Purchases',
								'Would you like to view your purchase history?',
								() => {
									window.location.href = 'purchases.php';
								}
							);
						} else {
							throw new Error(result.message || 'Failed to create order');
						}
					} catch (error) {
						console.error('Checkout failed:', error);
						this.showInfoMessage('Error', 'Failed to process checkout. Please try again.'); // Using custom message
					} finally {
						// Reset button state
						const checkoutBtn = document.querySelector('button[data-checkout]');
						if (checkoutBtn) {
							// Re-evaluate button disabled state based on current selected items
							const currentSelectedItemsCount = this.groupedItems.filter(item => item.selected).length;
							checkoutBtn.disabled = currentSelectedItemsCount === 0;
							checkoutBtn.innerHTML = originalContent;
						}
					}
				}
			);
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
			// This is a placeholder. For a real application, you'd integrate a custom toast/snackbar
			// or a more sophisticated modal for general info messages.
			console.log(`${title}: ${message}`); 
			// For now, if you want a visible (but non-native) alert, you could temporarily use:
			// alert(`${title}: ${message}`); 
		}
	},
	mounted() {
		this.reloadCart();
	}
}).mount('#cartApp');
