<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>CozyLoops - Handmade Crochet Creations</title>
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
	
	<div id="app">
		
		<!-- Hero Section with Grid -->
		<!-- Hero -->
		<section class="hero text-center" style="background-image: url('images/banner1.jpeg'); background-size: cover;">
			<h1 class="display-4">Handmade Crochet with Love</h1>
		</section>

		<!-- Categories Overview Section -->
		<section class="container mt-5">
			<div class="row mb-4">
				<div class="col-12 text-center">
					<h2>Shop by Category</h2>
					<p class="text-muted">Explore our handcrafted collections</p>
				</div>
			</div>
			
			<!-- Category Grid - Replace your existing category grid section with this -->
			<div class="row g-4 mb-5 justify-content-center">
				<div v-for="category in visibleCategories" :key="category.id" class="col-lg-2 col-md-4 col-sm-6 col-6">
					<div class="card index_category h-100" 
						 :class="category.id" 
						 @click="showCategoryInfo(category)"
						 tabindex="0"
						 @keydown.enter="showCategoryInfo(category)"
						 @keydown.space="showCategoryInfo(category)">
						<div class="category_card-body">
							<div>{{ category.icon }}</div>
							<h5 class="card-title">{{ category.name }}</h5>
							<p class="text-muted small">{{ getCategoryCount(category.id) }} items available</p>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- Best Sellers Section -->
		<section class="container mt-5">
			<div class="row mb-4">
				<div class="col-md-8">
					<h2><i class="fas fa-star text-warning me-2"></i>Best Sellers</h2>
					<p class="text-muted">Our most popular handcrafted items</p>
				</div>
			</div>
			
			<div class="row g-4 mb-5">
				<div 
					v-for="(product, index) in products.slice(0, 3)" 
					:key="product.id"
					class="col-lg-4 col-md-6"
				>
					<div class="card product-card h-100">
						<div class="position-relative">
							<img 
								:src="product.image" 
								:alt="product.name"
								class="card-img-top product-image"
							>
							<span class="badge bg-warning position-absolute top-0 end-0 m-2">
								#{{ index + 1 }}
							</span>
						</div>
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
		</section>

		<!-- New Arrivals Section -->
		<section class="container mt-5" v-if="products.length > 3">
			<div class="row mb-4">
				<div class="col-md-8">
					<h2><i class="fas fa-sparkles text-info me-2"></i>New Arrivals</h2>
					<p class="text-muted">Fresh additions to our collection</p>
				</div>
			</div>
			
			<div class="row g-4 mb-5">
				<div 
					v-for="product in products.slice(3, 6)" 
					:key="product.id"
					class="col-lg-4 col-md-6"
				>
					<div class="card product-card h-100">
						<div class="position-relative">
							<img 
								:src="product.image" 
								:alt="product.name"
								class="card-img-top product-image"
							>
							<span class="badge bg-success position-absolute top-0 end-0 m-2">
								New
							</span>
						</div>
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
		</section>

		<!-- Call to Action Grid Section -->
		<section class="container mt-5">
			<div class="row g-4 mb-5">
				<div class="col-md-8">
					<div class="explore-products-container">
						<h2>Explore All Products</h2>
						<p>Discover our complete collection of handmade crochet items crafted with love and attention to detail.</p>
						<a href="products.php" class="btn">
							<i class="fas fa-th me-2"></i>VIEW ALL PRODUCTS
						</a>
					</div>
				</div>
				<div class="col-md-4">
					<div class="made-with-love-container">
						<div class="heart-icon">♥</div>
						<h3>Made with Love</h3>
						<p>Every item crafted by hand with care and precision</p>
					</div>
				</div>
			</div>
		</section>

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
		
		<!-- Footer -->
		<?php include 'commons/footer.php'; ?>
	</div>

	<!-- Load Bootstrap JS first -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

	<!-- Load Vue and custom cart script -->
	<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
	<script src="index.js"></script>
</body>
</html>