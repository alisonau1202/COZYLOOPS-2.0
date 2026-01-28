<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Cart - CozyLoops</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	
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

	<div id="cartApp" class="container py-4">
		<h2 class="mb-4 text-center">🛒 Cozy Cart</h2>

		<div v-if="groupedItems.length === 0" class="text-center mt-5">
			<p class="lead">Your cart is empty... 🌸</p>
			<p>Start shopping to add some cozy items!</p>
		</div>

		<div v-else>
			<!-- Free Shipping Notice - MOVED TO TOP -->
			<div v-if="subtotal >= freeShippingThreshold && subtotal > 0" class="free-shipping-notice mb-3">
				<i class="fas fa-shipping-fast icon-spacing"></i> 
				Congratulations! You qualify for free shipping! 🎉
			</div>

			<!-- Shipping Progress - MOVED TO TOP -->
			<div v-else-if="subtotal > 0 && subtotal < freeShippingThreshold" class="shipping-progress mb-3">
				<i class="fas fa-truck icon-spacing"></i> 
				Add ${{ (freeShippingThreshold - subtotal).toFixed(2) }} more to qualify for free shipping!
				<div class="progress mt-2" style="height: 8px;">
					<div 
						class="progress-bar bg-warning" 
						:style="{ width: (subtotal / freeShippingThreshold * 100) + '%' }"
					></div>
				</div>
			</div>

			<!-- Cart Controls -->
			<div class="cart-container">
				<div class="row align-items-center">
					<div class="col-1 d-flex justify-content-center">
						<input 
							type="checkbox" 
							id="selectAll"
							@change="toggleSelectAll()"
							:checked="groupedItems.length > 0 && groupedItems.every(item => item.selected)"
						>
					</div>
					<div class="col-7">
						<label for="selectAll">
							Select All Items ({{ selectedItemsCount }} selected)
						</label>
					</div>
					<div class="col-4 text-end">
						<button class="remove-all-btn" @click="clearCart()">
							<i class="fas fa-trash icon-spacing"></i>Clear Cart
						</button>
					</div>
				</div>
			</div>

			<!-- Cart Items Container -->
			<div class="cart-container">
				<div class="cart-item row align-items-center" v-for="item in groupedItems" :key="item.id">
					<div class="col-1 d-flex justify-content-center">
						<input type="checkbox" v-model="item.selected" />
					</div>

					<div class="col-2">
						<img :src="item.image" class="img-fluid" :alt="item.name" />
					</div>

					<div class="col-5">
						<h5>{{ item.name }}</h5>
						<p class="mb-1">Price: ${{ item.price.toFixed(2) }}</p>
						<p class="mb-1">
							Quantity:
							<div class="btn-group btn-group-sm" role="group" aria-label="Quantity controls">
								<button
									class="btn btn-outline-secondary"
									@click="removeOne(item)"
									:disabled="item.quantity === 1"
								>-</button>
								<span class="btn btn-outline-primary disabled quantity-number">
									{{ item.quantity }}
								</span>
								<button class="btn btn-outline-secondary" @click="addOne(item)">+</button>
							</div>
						</p>
						<p>Total: ${{ (item.price * item.quantity).toFixed(2) }}</p>
					</div>

					<div class="col-4 text-end">
						<button class="remove-all-btn" @click="removeAll(item)">
							<i class="fas fa-trash"></i>
						</button>
					</div>
				</div>
			</div>

			<!-- Order Summary -->
			<div class="mt-4 total-price-section">
				<h5 class="mb-3">Order Summary</h5>
				
				<div class="d-flex justify-content-between mb-2">
					<span>Subtotal ({{ selectedItemsCount }} items):</span>
					<span>${{ subtotal.toFixed(2) }}</span>
				</div>
				
				<div class="d-flex justify-content-between mb-2">
					<span>Shipping:</span>
					<span v-if="postage === 0 && subtotal > 0" class="text-success">
						<i class="fas fa-check icon-spacing"></i>FREE
					</span>
					<span v-else-if="postage === 0">$0.00</span>
					<span v-else>${{ postage.toFixed(2) }}</span>
				</div>
				
				<hr>
				
				<div class="d-flex justify-content-between">
					<h5>Total:</h5>
					<h5 class="text-primary">${{ totalPrice.toFixed(2) }}</h5>
				</div>
				
				<div class="mt-3 text-center">
					<button 
						class="btn btn-brown btn-lg"
						:disabled="selectedItemsCount === 0"
						@click="proceedToCheckout"
					>
						<i class="fas fa-credit-card icon-spacing"></i>Proceed to Checkout
					</button>
				</div>
			</div>
		</div>

		<!-- Confirmation Dialog (Custom Modal) - MOVED INSIDE #cartApp -->
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
						<button type="button" class="btn profile-btn cancel-btn" data-bs-dismiss="modal" @click="closeConfirm">Cancel</button>
						<button type="button" class="btn profile-btn delete-btn" @click="executeConfirmAction">Confirm</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Footer -->
	<?php include 'commons/footer.php'; ?>

	<!-- Load Bootstrap JS first -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

	<!-- Load Vue and custom cart script -->
	<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
	<script src="cart.js"></script>
</body>
</html>