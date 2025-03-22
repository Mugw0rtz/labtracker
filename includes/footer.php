</main>
        
        <!-- Footer -->
        <footer class="py-3 bg-light mt-auto">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - Version <?php echo APP_VERSION; ?></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-0">Laboratory Tool Management System</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- QR Scanner Library -->
    <script src="/js/qr-scanner.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="/js/main.js"></script>
    
    <!-- Check for new notifications if user is logged in -->
    <?php if (isLoggedIn()): ?>
    <script>
        // Check for notifications every 60 seconds
        $(document).ready(function() {
            checkNotifications();
            setInterval(checkNotifications, 60000);
        });
        
        function checkNotifications() {
            $.ajax({
                url: '/api/notifications.php?action=count',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.count > 0) {
                        $('#notificationBadge').text(response.count);
                        $('#notificationBadge').show();
                    } else {
                        $('#notificationBadge').hide();
                    }
                }
            });
        }
    </script>
    <?php endif; ?>
    
    <?php if (isset($extraJS)) { echo $extraJS; } ?>
</body>
</html>
