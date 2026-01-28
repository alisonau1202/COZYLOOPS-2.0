const { createApp } = Vue;

createApp({
	data() {
		return {
			products: [],
			selectedProduct: null,
			loading: true,
			error: null,
			message: '',
			isSuccess: false,
			isError: false,
			modalInstance: null,
			
			// Category definitions for product categorization
			categories: [
				{ id: 'all', name: 'All Products', icon: '🧶' },
				{ id: 'accessories', name: 'Accessories', icon: '👒' },
				{ id: 'clothing', name: 'Clothing', icon: '👕' },
				{ id: 'home', name: 'Home Decor', icon: '🏠' },
				{ id: 'baby', name: 'Baby Items', icon: '👶' },
				{ id: 'seasonal', name: 'Seasonal', icon: '🎄' }
			]
		};
	},
	
	computed: {
		// Show only 5 categories for the grid display
		visibleCategories() {
			return this.categories.slice(1); // Skip 'all' and show 5 categories
		},
		
		// Group products by category for better organization
		productsByCategory() {
			const grouped = {};
			this.categories.forEach(cat => {
				grouped[cat.id] = this.products.filter(product => 
					this.getProductCategoryId(product) === cat.id
				);
			});
			return grouped;
		},
		
		// Get featured products (first 3)
		featuredProducts() {
			return this.products.slice(0, 3);
		},
		
		// Get new arrivals (next 3 products)
		newArrivals() {
			return this.products.slice(3, 6);
		}
	},
	
	methods: {
		// Enhanced modal opening method
		openProductModal(product) {
			this.selectedProduct = product;
			// Initialize Bootstrap modal if not already done
			if (!this.modalInstance) {
				const modalElement = document.getElementById('productModal');
				this.modalInstance = new bootstrap.Modal(modalElement);
				
				// Add event listener for modal close
				modalElement.addEventListener('hidden.bs.modal', () => {
					this.selectedProduct = null;
					this.message = '';
					this.isSuccess = false;
					this.isError = false;
				});
			}
			this.modalInstance.show();
		},
		
		// Keep your existing openModal method for compatibility
		openModal(product) {
			this.openProductModal(product);
		},
		
		async fetchProducts() {
			try {
				const response = await fetch('products.json');
				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				const data = await response.json();
				this.products = data;
				console.log('Successfully loaded products from products.json:', this.products.length);
			} catch (error) {
				this.error = 'Error loading products from products.json: ' + error.message;
				console.error('Error loading products:', error);
				console.error('Make sure products.json exists and is properly formatted');
			} finally {
				this.loading = false;
			}
		},
		
		addToCart(product) {
			fetch('cart_api.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					id: product.id,
					name: product.name,
					price: product.price,
					image: product.image
				})
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					this.showMessage(data.message || 'Added to cart!', true);
				} else {
					this.showMessage(data.message || 'Failed to add to cart.', false);
				}
			})
			.catch(error => {
				console.error('Error adding to cart:', error);
				this.showMessage('Error adding to cart.', false);
			});
		},
		
		showMessage(text) {
			this.message = text;
			setTimeout(() => {
				this.message = '';
			}, 2000);
		},
		
		// Keep your existing showMessage method for compatibility
		showMessageOld(msg, success = true) {
			this.message = msg;
			this.isSuccess = success;
			this.isError = !success;
			setTimeout(() => {
				this.message = '';
				this.isSuccess = false;
				this.isError = false;
			}, 3000);
		},
		
		// UPDATED: Navigate to products page with category filter
		showCategoryInfo(category) {
			// Navigate to products page with the selected category filter
			window.location.href = `products.php?category=${category.id}`;
		},
		
		// Get count of products in each category
		getCategoryCount(categoryId) {
			if (categoryId === 'all') {
				return this.products.length;
			}
			return this.products.filter(product => 
				this.getProductCategoryId(product) === categoryId
			).length;
		},
		
		// Enhanced product categorization helper methods
		getProductCategoryId(product) {
			const name = product.name.toLowerCase();
			const desc = product.description.toLowerCase();
			
			// Baby Items
			if (name.includes('baby') || name.includes('booties') || desc.includes('nursery decor') || desc.includes('baby’s feet')) {
				return 'baby';
			}
			// Seasonal Items (e.g., Christmas)
			else if (name.includes('christmas') || name.includes('holiday') || name.includes('stocking')) {
				return 'seasonal';
			}
			// Home Decor
			else if (name.includes('pillow') || name.includes('plant') ||
							 name.includes('pot cover') || desc.includes('home decor')) {
				return 'home';
			}
			// Clothing
			else if (name.includes('hat') || name.includes('scarf') || name.includes('headband') ||
							 name.includes('sweater')) { // Removed 'cozy' from clothing for now
				return 'clothing';
			}
			// Accessories (This is the default/fallback if no other category matches)
			// Including "Soft Crochet Blanket" and "Crochet Coffee Cup Cozy" here
			else {
				return 'accessories';
			}
		},
		
		getProductCategory(product) {
			const categoryId = this.getProductCategoryId(product);
			const category = this.categories.find(cat => cat.id === categoryId);
			return category ? category.name : 'Accessories';
		},
		
		// Enhanced filtering method for future use
		filterProductsByCategory(categoryId) {
			if (categoryId === 'all') {
				return this.products;
			}
			return this.products.filter(product => 
				this.getProductCategoryId(product) === categoryId
			);
		},
		
		// Format helpers
		formatPrice(price) {
			return parseFloat(price).toFixed(2);
		},
		
		truncateText(text, maxLength) {
			if (!text) return '';
			if (text.length <= maxLength) return text;
			return text.substring(0, maxLength).trim() + '...';
		},
		
		// Get category statistics for display
		getCategoryStats() {
			const stats = {};
			this.categories.forEach(category => {
				if (category.id !== 'all') {
					stats[category.id] = {
						name: category.name,
						count: this.getCategoryCount(category.id),
						icon: category.icon
					};
				}
			});
			return stats;
		}
	},
	
	mounted() {
		this.fetchProducts();
		
		// Log category distribution for debugging
		this.$nextTick(() => {
			setTimeout(() => {
				if (this.products.length > 0) {
					console.log('Category distribution:', this.getCategoryStats());
				}
			}, 1000);
		});
	}
}).mount('#app');