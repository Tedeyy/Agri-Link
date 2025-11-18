document.addEventListener('DOMContentLoaded', function() {
  // Mobile hamburger menu functionality
  const hamburger = document.querySelector('.hamburger');
  const navRight = document.querySelector('.nav-right');
  const navCenter = document.querySelector('.nav-center');
  
  if (hamburger && navRight && navCenter) {
    // Clone nav-center buttons to mobile menu
    const mobileMenuButtons = navCenter.querySelectorAll('.btn');
    mobileMenuButtons.forEach(button => {
      const clonedButton = button.cloneNode(true);
      navRight.appendChild(clonedButton);
    });
    
    hamburger.addEventListener('click', function() {
      hamburger.classList.toggle('active');
      navRight.classList.toggle('mobile-open');
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
      if (!hamburger.contains(event.target) && !navRight.contains(event.target)) {
        hamburger.classList.remove('active');
        navRight.classList.remove('mobile-open');
      }
    });
    
    // Close menu when window is resized to desktop size
    window.addEventListener('resize', function() {
      if (window.innerWidth > 768) {
        hamburger.classList.remove('active');
        navRight.classList.remove('mobile-open');
      }
    });
  }

  // Swipe-to-open functionality
  let touchStartX = 0;
  let touchEndX = 0;
  let touchStartY = 0;
  let touchEndY = 0;
  const swipeThreshold = 50; // Minimum distance for swipe
  const swipeEdgeThreshold = 20; // Must start within 20px of left edge
  
  function handleTouchStart(e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
  }
  
  function handleTouchEnd(e) {
    if (!hamburger || !navRight) return;
    
    touchEndX = e.changedTouches[0].clientX;
    touchEndY = e.changedTouches[0].clientY;
    
    const deltaX = touchEndX - touchStartX;
    const deltaY = Math.abs(touchEndY - touchStartY);
    
    // Check if it's a horizontal swipe (not vertical scroll)
    if (Math.abs(deltaX) > swipeThreshold && deltaY < 50) {
      // Check if swipe started from left edge and is moving right
      if (touchStartX <= swipeEdgeThreshold && deltaX > 0) {
        // Swipe right from left edge - open menu
        hamburger.classList.add('active');
        navRight.classList.add('mobile-open');
      }
      // Check if swipe is moving left and menu is open
      else if (deltaX < 0 && navRight.classList.contains('mobile-open')) {
        // Swipe left - close menu
        hamburger.classList.remove('active');
        navRight.classList.remove('mobile-open');
      }
    }
  }
  
  // Add touch event listeners to document
  document.addEventListener('touchstart', handleTouchStart, { passive: true });
  document.addEventListener('touchend', handleTouchEnd, { passive: true });
});
