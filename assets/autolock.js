// autolock.js
(function() {
    const IDLE_TIMEOUT = 50000; // 30 Seconds
    let idleTimer;

    // Don't run this on the login page itself
    if (window.location.pathname.endsWith('index.php') || window.location.pathname === '/') {
        return;
    }

    function resetTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(performLogout, IDLE_TIMEOUT);
    }

    function performLogout() {
        // Use the base_url we defined in our PHP header
        const baseUrl = window.FOGS_BASE_URL || '/fogs-1';
        
        // Redirecting to logout.php triggers the server-side session_destroy()
        window.location.href = baseUrl + '/logout.php';
    }

    // Events to track (Touch for tablets, Mouse/Keys for PC)
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
    events.forEach(name => {
        document.addEventListener(name, resetTimer, true);
    });

    resetTimer();
})();