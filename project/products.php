<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>Product Showcase - CozyLoops</title>
	<meta name="viewport" content="width=device-width, initial-scale=1" />

	<!-- Bootstrap-->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	
	<!-- Font Awesome & Fonts -->
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700&display=swap" rel="stylesheet">
	
	<!-- Custom Styles -->
	<link href="style/style.css" rel="stylesheet">
</head>
<body>
	<!-- Header -->
	<?php include 'commons/header.php'; ?>

	<div id="app" class="container my-5">
		<div class="text-center mb-4">
			<h2 class="mb-4 text-center">🧶 Product Showcase</h2>
		</div>

		<!-- Category Filter Tabs (Demonstrating Arrays & Selection Directives) -->
		<div class="category-tabs">
			<h5 class="mb-3 text-center">
				<i class="fas fa-tags icon-spacing"></i>Browse by Category
			</h5>
			<div class="text-center">
				<button 
					v-for="category in categories" 
					:key="category.id"
					:class="['btn', 'category-btn', { active: selectedCategory === category.id }]"
					@click="selectCategory(category.id)"
				>
					{{ category.icon }} {{ category.name }} 
					<span class="badge bg-light text-dark ms-1">{{ category.count }}</span>
				</button>
			</div>
		</div>

		<!-- Advanced Filters Section -->
		<div class="filter-section">
			<div class="row align-items-end">
				<div class="col-md-4">
					<label class="form-label">
						<i class="fas fa-search icon-spacing"></i>Search Products
					</label>
					<div class="position-relative">
						<input 
							type="text" 
							class="form-control"
							v-model.trim="searchQuery"
							placeholder="Search names, descriptions..."
							@input="resetPagination"
						>
						<!-- Clear button (X) positioned on the right side -->
						<button 
							v-show="searchQuery.trim() !== ''"
							@click="searchQuery = ''; resetPagination()"
							class="btn position-absolute"
							style="right: 8px; top: 50%; transform: translateY(-50%); border: none; background: transparent; color: #6c757d; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s ease;"
							onmouseover="this.style.backgroundColor='#f8f9fa'; this.style.color='#495057';"
							onmouseout="this.style.backgroundColor='transparent'; this.style.color='#6c757d';"
							type="button"
							title="Clear search"
						>
							<i class="fas fa-times" style="font-size: 14px;"></i>
						</button>
					</div>
				</div>
				<div class="col-md-2">
					<label class="form-label">
						<i class="fas fa-dollar-sign icon-spacing"></i>Price Range
					</label>
					<select class="form-select" v-model="priceRange" @change="resetPagination">
						<option v-for="range in priceRanges" :key="range.value" :value="range.value">
							{{ range.label }}
						</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">
						<i class="fas fa-sort icon-spacing"></i>Sort By
					</label>
					<select class="form-select" v-model="sortBy">
						<option v-for="option in sortOptions" :key="option.value" :value="option.value">
							{{ option.label }}
						</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Items Per Page</label>
					<select class="form-select" v-model.number="itemsPerPage" @change="resetPagination">
						<option v-for="size in pageSizes" :key="size" :value="size">{{ size }}</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">View Mode</label>
					<div class="view-mode-toggle d-flex">
						<button 
							:class="['view-btn', 'flex-fill', { active: viewMode === 'grid' }]"
							@click="viewMode = 'grid'"
						>
							<i class="fas fa-th"></i>
						</button>
						<button 
							:class="['view-btn', 'flex-fill', { active: viewMode === 'list' }]"
							@click="viewMode = 'list'"
						>
							<i class="fas fa-list"></i>
						</button>
					</div>
				</div>
			</div>

			<!-- Active Filters Display -->
			<div v-show="hasActiveFilters" class="mt-3 pt-3 border-top">
				<small class="text-muted me-2">Active Filters:</small>
				<span v-if="searchQuery" class="badge bg-primary me-2">
					Search: "{{ searchQuery }}" <i class="fas fa-times ms-1" @click="searchQuery = ''" style="cursor: pointer;"></i>
				</span>
				<span v-if="selectedCategory !== 'all'" class="badge bg-success me-2">
					Category: {{ getCategoryName(selectedCategory) }} <i class="fas fa-times ms-1" @click="selectedCategory = 'all'" style="cursor: pointer;"></i>
				</span>
				<span v-if="priceRange !== 'all'" class="badge bg-warning me-2">
					Price: {{ getPriceRangeLabel(priceRange) }} <i class="fas fa-times ms-1" @click="priceRange = 'all'" style="cursor: pointer;"></i>
				</span>
				<button class="btn btn-sm btn-outline-danger" @click="clearAllFilters">
					<i class="fas fa-refresh icon-spacing"></i>Clear All
				</button>
			</div>
		</div>

		<!-- Results Summary with Pagination Info -->
		<div class="d-flex justify-content-between align-items-center mb-4" v-if="filteredProducts.length > 0">
			<span class="info-bubble me-3">
				<strong>{{ filteredProducts.length }}</strong> product<span v-if="filteredProducts.length !== 1">s</span> found
				<span v-if="hasActiveFilters" class="text-muted ms-2">
					(filtered from {{ products.length }} total)
				</span>
			</span>
			<span class="info-bubble">
				<span v-if="totalPages > 1">Page {{ currentPage }} of {{ totalPages }} </span>
				({{ startIndex }}-{{ endIndex }} of {{ filteredProducts.length }})
			</span>
		</div>

		<!-- No Products Message (Conditional Rendering Directive) -->
		<div v-if="filteredProducts.length === 0 && !loading" class="no-products">
			<i class="fas fa-search fa-4x text-muted mb-4"></i>
			<h3>No Products Found</h3>
			<p class="text-muted mb-4">
				<span v-if="hasActiveFilters">
					Try adjusting your filters or search terms
				</span>
				<span v-else>
					No products are currently available
				</span>
			</p>
			<button v-if="hasActiveFilters" class="btn btn-brown" @click="clearAllFilters">
				<i class="fas fa-refresh icon-spacing"></i>Show All Products
			</button>
		</div>

		<!-- Loading State -->
		<div v-if="loading" class="text-center py-5">
			<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
				<span class="visually-hidden">Loading...</span>
			</div>
			<p class="mt-3 text-muted">Loading products...</p>
		</div>

		<!-- Products Display (Repetition Directives with Dynamic Layout) -->
		<div v-if="!loading && filteredProducts.length > 0">
			<!-- Grid View -->
			<div v-if="viewMode === 'grid'" class="row g-4">
				<div 
					v-for="(product, index) in paginatedProducts" 
					:key="product.id"
					:class="gridColumnClass"
				>
					<div class="card product-card h-100">
						<img 
							:src="product.image" 
							:alt="product.name"
							class="card-img-top product-image"
						>
						<div class="card-body">
							<h5 class="card-title">{{ product.name }}</h5>
							<p class="card-text text-muted small">{{ truncateText(product.description, 80) }}</p>
							<div class="d-flex justify-content-between align-items-center">
								<span class="price-badge">${{ formatPrice(product.price) }}</span>
								<small class="text-muted">{{ getProductCategory(product) }}</small>
							</div>
						</div>
						<div class="card-footer bg-transparent">
							<button class="btn btn-custom w-100" @click="openProductModal(product)">
								<i class="fas fa-eye icon-spacing"></i>View Details
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- List View -->
			<div v-else class="list-view">
				<div 
					v-for="(product, index) in paginatedProducts" 
					:key="product.id"
					class="card product-card mb-3"
				>
					<div class="row g-0">
						<div class="col-md-3">
							<img 
								:src="product.image" 
								:alt="product.name"
								class="img-fluid h-100"
								style="object-fit: cover; border-radius: 15px 0 0 15px;"
							>
						</div>
						<div class="col-md-9">
							<div class="card-body d-flex justify-content-between">
								<div>
									<h5 class="card-title">{{ product.name }}</h5>
									<p class="card-text">{{ product.description }}</p>
									<small class="text-muted">Category: {{ getProductCategory(product) }}</small>
								</div>
								<div class="text-end">
									<div class="price-badge mb-2">${{ formatPrice(product.price) }}</div>
									<button class="btn btn-custom" @click="openProductModal(product)">
										<i class="fas fa-eye icon-spacing"></i>View
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Enhanced Pagination Controls -->
		<div v-if="totalPages > 1" class="mt-5">
			<div class="d-flex justify-content-center align-items-center">
				
				<paginate
					v-model="currentPage"
					:page-count="totalPages"
					:click-handler="changePage"
					:prev-text="'Prev'"
					:next-text="'Next'"
					:container-class="'pagination justify-content-center mb-0'"
					:page-class="'page-item'"
					:page-link-class="'page-link btn-custom'"
					:prev-class="'page-item'"
					:prev-link-class="'page-link btn-custom'"
					:next-class="'page-item'"
					:next-link-class="'page-link btn-custom'"
					:active-class="'active'"
					:disabled-class="'disabled'"
					:page-range="5"
				/>
				
				<button 
					class="btn btn-outline-primary ms-2"
					:disabled="currentPage === totalPages"
					@click="changePage(totalPages)"
				>
					<i class="fas fa-angle-double-right"></i>
				</button>
			</div>
			
			<!-- Jump to Page -->
			<div class="text-center mt-3">
				<small class="text-muted me-2">Jump to page:</small>
				<input 
					type="number" 
					class="form-control d-inline-block"
					style="width: 80px;"
					:min="1" 
					:max="totalPages"
					v-model.number="jumpToPage"
					@keyup.enter="goToPage"
				>
				<button class="btn-brown ms-2" @click="goToPage">Go</button>
			</div>
		</div>

		<!-- Enhanced Product Detail Modal -->
		<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
			<div v-if="message" class="alert alert-success position-fixed top-0 end-0 m-3" style="z-index: 9999;">
				{{ message }}
			</div>
			<div class="modal-dialog modal-lg modal-dialog-centered">
				<div class="modal-content custom-card" v-if="selectedProduct">
					<div class="modal-header">
						<h5 class="modal-title">{{ selectedProduct.name }}</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div class="row">
							<div class="col-md-6">
								<img :src="selectedProduct.image" class="img-fluid rounded" :alt="selectedProduct.name">
							</div>
							<div class="col-md-6">
								<h4 class="text-success mb-3">${{ formatPrice(selectedProduct.price) }}</h4>
								<p>{{ selectedProduct.description }}</p>
								<hr>
								<div class="row">
									<div class="col-6">
										<p><strong>Category:</strong><br>{{ getProductCategory(selectedProduct) }}</p>
									</div>
									<div class="col-6">
										<p><strong>Status:</strong><br><span class="badge bg-success">In Stock</span></p>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn profile-btn cancel-btn" data-bs-dismiss="modal">Close</button>
						<button type="button" class="btn profile-btn delete-btn" @click="addToCart(selectedProduct)">
							<i class="fas fa-cart-plus icon-spacing"></i>Add to Cart
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Footer -->
	<?php include 'commons/footer.php'; ?>
	
	<!-- Load Bootstrap JS first -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	
	<!-- Load Vue -->
	<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>	

	<!-- VuePaginate -->
	<script src="https://unpkg.com/vuejs-paginate-next@latest/dist/vuejs-paginate-next.umd.js"></script>
	
	<!-- Load custom cart script -->
	<script src="products.js"></script>
</body>
</html>