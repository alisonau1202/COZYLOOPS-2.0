const { createApp, watch, onMounted } = Vue; // Import watch and onMounted

createApp({
	components: {
		paginate: VuejsPaginateNext,
	},
	data() {
		return {
			// Core product data (ARRAY DEMONSTRATION)
			products: [],
			selectedProduct: null,
			loading: false,
			message: '',
			modalInstance: null,

			// Filter and search arrays (ARRAY DEMONSTRATION)
			categories: [
				{ id: 'all', name: 'All Products', icon: '🧶', count: 0 },
				{ id: 'accessories', name: 'Accessories', icon: '👒', count: 0 },
				{ id: 'clothing', name: 'Clothing', icon: '👕', count: 0 },
				{ id: 'home', name: 'Home Decor', icon: '🏠', count: 0 },
				{ id: 'baby', name: 'Baby Items', icon: '👶', count: 0 },
				{ id: 'seasonal', name: 'Seasonal', icon: '🎄', count: 0 }
			],

			priceRanges: [
				{ value: 'all', label: 'All Prices' },
				{ value: '0-10', label: 'Under $10' },
				{ value: '10-20', label: '$10 - $20' },
				{ value: '20-30', label: '$20 - $30' },
				{ value: '30-50', label: '$30 - $50' },
				{ value: '50+', label: '$50+' }
			],

			sortOptions: [
				{ value: 'name_asc', label: 'Name (A-Z)' },
				{ value: 'name_desc', label: 'Name (Z-A)' },
				{ value: 'price_low', label: 'Price: Low to High' },
				{ value: 'price_high', label: 'Price: High to Low' },
				{ value: 'id_asc', label: 'Newest First' },
				{ value: 'id_desc', label: 'Oldest First' }
			],

			pageSizes: [6, 9, 12, 18, 24],

			// Filter states (SELECTION DIRECTIVES) - Initialize from URL or defaults
			selectedCategory: this.getInitialUrlParam('category', 'all'),
			searchQuery: this.getInitialUrlParam('q', ''),
			priceRange: this.getInitialUrlParam('price', 'all'),
			sortBy: this.getInitialUrlParam('sort', 'name_asc'),
			viewMode: this.getInitialUrlParam('view', 'grid'),

			// Pagination states (PAGINATION DEMONSTRATION) - Initialize from URL or defaults
			currentPage: parseInt(this.getInitialUrlParam('page', 1)),
			itemsPerPage: parseInt(this.getInitialUrlParam('limit', 9)),
			jumpToPage: parseInt(this.getInitialUrlParam('page', 1)) // Keep jumpToPage in sync
		};
	},

	computed: {
		// FILTER DEMONSTRATION - Complex filtering with multiple conditions
		filteredProducts() {
			let filtered = [...this.products];

			// Category filter
			if (this.selectedCategory !== 'all') {
				filtered = filtered.filter(product => {
					return this.getProductCategoryId(product) === this.selectedCategory;
				});
			}

			// Search filter (name and description)
			if (this.searchQuery.trim()) {
				const query = this.searchQuery.toLowerCase().trim();
				filtered = filtered.filter(product =>
					product.name.toLowerCase().includes(query) ||
					product.description.toLowerCase().includes(query)
				);
			}

			// Price range filter
			if (this.priceRange !== 'all') {
				filtered = filtered.filter(product => {
					const price = product.price;
					switch (this.priceRange) {
						case '0-10': return price < 10;
						case '10-20': return price >= 10 && price < 20;
						case '20-30': return price >= 20 && price < 30;
						case '30-50': return price >= 30 && price < 50;
						case '50+': return price >= 50;
						default: return true;
					}
				});
			}

			// Sorting
			return filtered.sort((a, b) => {
				switch (this.sortBy) {
					case 'name_desc':
						return b.name.localeCompare(a.name);
					case 'price_low':
						return a.price - b.price;
					case 'price_high':
						return b.price - a.price;
					case 'id_desc': // Newest First - higher ID is newer
						return b.id - a.id;
					case 'id_asc': // Oldest First - lower ID is older
						return a.id - b.id;
					default: // name_asc
						return a.name.localeCompare(b.name);
				}
			});
		},

		// PAGINATION COMPUTATIONS
		totalProductsCount() { // New computed property for total count of filtered products
				return this.filteredProducts.length;
		},

		totalPages() {
			return Math.ceil(this.totalProductsCount / this.itemsPerPage);
		},

		paginatedProducts() {
			const start = (this.currentPage - 1) * this.itemsPerPage;
			const end = start + this.itemsPerPage;
			return this.filteredProducts.slice(start, end);
		},

		startIndex() {
			return (this.currentPage - 1) * this.itemsPerPage + 1;
		},

		endIndex() {
			return Math.min(this.currentPage * this.itemsPerPage, this.filteredProducts.length);
		},

		// ARRAY COMPUTATIONS - Statistical calculations
		averagePrice() {
			if (this.products.length === 0) return '0.00';
			const total = this.products.reduce((sum, product) => sum + product.price, 0);
			return (total / this.products.length).toFixed(2);
		},

		uniqueCategories() {
			const categoryIds = new Set();
			this.products.forEach(product => {
				categoryIds.add(this.getProductCategoryId(product));
			});
			return Array.from(categoryIds);
		},

		// CONDITIONAL RENDERING HELPERS
		hasActiveFilters() {
			return this.selectedCategory !== 'all' ||
						 this.searchQuery.trim() !== '' ||
						 this.priceRange !== 'all' ||
						 this.sortBy !== 'name_asc'; // Also consider default sort as an active filter if changed
		},

		gridColumnClass() {
			// Dynamic grid sizing based on items per page
			switch (this.itemsPerPage) {
				case 6: return 'col-lg-4 col-md-6';
				case 9: return 'col-lg-4 col-md-6';
				case 12: return 'col-xl-3 col-lg-4 col-md-6';
				case 18: return 'col-xl-3 col-lg-4 col-md-6 col-sm-6';
				case 24: return 'col-xl-2 col-lg-3 col-md-4 col-sm-6';
				default: return 'col-lg-4 col-md-6';
			}
		}
	},

	watch: {
		// Watch for changes in filter and pagination states to update URL
		selectedCategory: 'updateUrl',
		searchQuery: 'updateUrl',
		priceRange: 'updateUrl',
		sortBy: 'updateUrl',
		currentPage: 'updateUrl',
		itemsPerPage: 'updateUrl',
		viewMode: 'updateUrl',

		// Auto-reset pagination when filters change (still necessary)
		filteredProducts() {
			// Only reset if the current page is out of bounds for the new filtered results
			if (this.currentPage > this.totalPages && this.totalPages > 0) {
				this.currentPage = 1;
				this.jumpToPage = 1;
			} else if (this.totalPages === 0) { // If no products match filters
					this.currentPage = 1;
					this.jumpToPage = 1;
			}
		},

		// Update category counts when products change
		products: {
			handler() {
				this.updateCategoryCounts();
			},
			deep: true
		}
	},

	methods: {
		// Helper to get initial URL parameter
		getInitialUrlParam(paramName, defaultValue) {
				const urlParams = new URLSearchParams(window.location.search);
				return urlParams.get(paramName) || defaultValue;
		},

		// Method to update URL query parameters
		updateUrl() {
				const urlParams = new URLSearchParams();

				if (this.selectedCategory !== 'all') {
						urlParams.set('category', this.selectedCategory);
				}
				if (this.searchQuery.trim() !== '') {
						urlParams.set('q', this.searchQuery.trim());
				}
				if (this.priceRange !== 'all') {
						urlParams.set('price', this.priceRange);
				}
				if (this.sortBy !== 'name_asc') { // Only add if not default
						urlParams.set('sort', this.sortBy);
				}
				if (this.currentPage !== 1) { // Only add if not default
						urlParams.set('page', this.currentPage);
				}
				if (this.itemsPerPage !== 9) { // Only add if not default
						urlParams.set('limit', this.itemsPerPage);
				}
				if (this.viewMode !== 'grid') { // Only add if not default
						urlParams.set('view', this.viewMode);
				}

				// Corrected template literal syntax
				const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
				// Use replaceState to avoid cluttering history with every key press for search,
				// but pushState is also an option if you want every filter change in history.
				// For search, replaceState is generally better for UX.
				window.history.replaceState({}, '', newUrl);
		},

		// ARRAY MANIPULATION METHODS
		selectCategory(categoryId) {
			this.selectedCategory = categoryId;
			// Pagination reset is handled by the filteredProducts watcher
		},

		updateCategoryCounts() {
			// Reset all counts
			this.categories.forEach(cat => cat.count = 0);

			// Count products in each category
			this.products.forEach(product => {
				const categoryId = this.getProductCategoryId(product);
				const category = this.categories.find(cat => cat.id === categoryId);
				if (category) category.count++;
			});

			// Update "All Products" count
			const allCategory = this.categories.find(cat => cat.id === 'all');
			if (allCategory) allCategory.count = this.products.length;
		},

		// FILTER HELPER METHODS (FORMAT & FILTER DEMONSTRATIONS)
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

		getCategoryName(categoryId) {
			const category = this.categories.find(cat => cat.id === categoryId);
			return category ? category.name : 'Unknown';
		},

		getPriceRangeLabel(rangeValue) {
			const range = this.priceRanges.find(r => r.value === rangeValue);
			return range ? range.label : 'All Prices';
		},

		// FORMAT FILTERS
		formatPrice(price) {
			return parseFloat(price).toFixed(2);
		},

		truncateText(text, maxLength) {
			if (text.length <= maxLength) return text;
			return text.substring(0, maxLength).trim() + '...';
		},

		// PAGINATION METHODS
		changePage(page) {
			// Ensure page is within valid range before updating
			const newPage = Math.max(1, Math.min(page, this.totalPages > 0 ? this.totalPages : 1));
			if (this.currentPage !== newPage) { // Only update if actual change
				this.currentPage = newPage;
				this.jumpToPage = newPage;
				this.scrollToTop();
			}
		},

		goToPage() {
			// Ensure jumpToPage is a number and within valid range
			const targetPage = parseInt(this.jumpToPage);
			if (!isNaN(targetPage) && targetPage >= 1 && targetPage <= this.totalPages) {
				this.changePage(targetPage);
			} else {
				this.jumpToPage = this.currentPage; // Reset input if invalid
			}
		},

		resetPagination() {
			this.currentPage = 1;
			this.jumpToPage = 1;
		},

		scrollToTop() {
			// This will scroll to the h1 element with ID 'products-heading' if you add one,
			// or the app div if it's the top-most element with an ID.
			// Make sure there's an element to scroll to, e.g., a heading or the main product container.
			const targetElement = document.getElementById('products-heading') || document.querySelector('.product-list-section');
			if (targetElement) {
					targetElement.scrollIntoView({
							behavior: 'smooth',
							block: 'start'
					});
			} else {
					window.scrollTo({ top: 0, behavior: 'smooth' });
			}
		},

		// FILTER MANAGEMENT
		clearAllFilters() {
			this.selectedCategory = 'all';
			this.searchQuery = '';
			this.priceRange = 'all';
			this.sortBy = 'name_asc';
			this.itemsPerPage = 9; // Reset items per page too
			this.resetPagination(); // This will trigger updateUrl via watcher
		},

		// MODAL METHODS
		openProductModal(product) {
			this.selectedProduct = product;
			// Initialize Bootstrap modal if not already done
			if (!this.modalInstance) {
				const modalElement = document.getElementById('productModal');
				// Ensure Bootstrap is loaded before trying to instantiate Modal
				if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
					this.modalInstance = new bootstrap.Modal(modalElement);
				} else {
					console.warn('Bootstrap Modal not found. Make sure Bootstrap JS is loaded.');
					// Fallback if Bootstrap isn't loaded, e.g., show a simple alert
					// Corrected template literal syntax
					alert(`Product: ${product.name}\nPrice: $${product.price}\nDescription: ${product.description}`);
					return;
				}
			}
			this.modalInstance.show();
		},

		closeProductModal() {
			if (this.modalInstance) {
				this.modalInstance.hide();
			}
			this.selectedProduct = null;
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

		showMessage(text, isSuccess = true) { // Added isSuccess parameter for styling
			this.message = text;
			// You'll need to pass 'isSuccess' / 'isError' state to the template for styling
			// For simplicity, using one 'message' here. If you have different styles,
			// you might need additional ref properties like isMessageSuccess, isMessageError.
			setTimeout(() => {
				this.message = '';
			}, 1000);
		},

		// DATA LOADING METHODS
		async loadProducts() {
			this.loading = true;
			try {
				// Load products from products.json
				const response = await fetch('products.json');
				if (!response.ok) {
					// Corrected template literal syntax
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				const data = await response.json();
				this.products = data;
				this.updateCategoryCounts();
			} catch (error) {
				console.error('Error loading products:', error);
				this.showMessage('Error loading products. Please check if products.json exists.');
				// Fallback to empty array
				this.products = [];
			} finally {
				this.loading = false;
			}
		}
	},

	// LIFECYCLE HOOKS
	mounted() {
		this.loadProducts();

		// Initialize modal event listeners
		const modalElement = document.getElementById('productModal');
		if (modalElement) {
			modalElement.addEventListener('hidden.bs.modal', () => {
				this.selectedProduct = null;
			});
		}

		// Initial URL sync for default values if no params are present
		// This is important if a user visits your page without any query params
		// and you want to ensure the URL accurately reflects the *default* state.
		this.updateUrl();
	}
}).mount('#app');