<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Profile Page</title>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	
	<!-- Vuetify CSS and Bootstrap-->
	<link href="https://cdn.jsdelivr.net/npm/vuetify@3.5.0/dist/vuetify.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	
	<!-- Font Awesome & Fonts -->
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/@mdi/font@latest/css/materialdesignicons.min.css" rel="stylesheet">
	
	<!-- Vue & Vuetify JS -->
	<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/vuetify@3.5.0/dist/vuetify.min.js"></script>
	
	<!-- Custom Styles & Script -->
	<link rel="stylesheet" href="style/style.css">
</head>
<body>
	<?php include 'commons/header.php'; ?>
	
	<div id="profile-app"></div>
	
	<?php include 'commons/footer.php'; ?>
	
	<script>
		window.currentUser = <?php echo json_encode($user); ?>;
	</script>
	
	<!-- Load Bootstrap JS first -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	
	<!-- Load your profile Vue app last -->
	<script src="profile.js"></script>
</body>
</html>