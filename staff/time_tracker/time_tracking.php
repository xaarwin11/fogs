<?php
require_once __DIR__ . '/../../db.php';?>
<script src="<?php echo $base_url; ?>/assets/autolock.js"></script>
<?php
session_start();


if (empty($_SESSION['user_id'])) {
    header("Location: $base_url/index.php");
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) {
    header("Location: $base_url/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Time Tracking</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/style.css">
    <style>
        .time-tracking-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .time-status-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 30px;
        }
        
        .status-display {
            font-size: 18px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .status-display.clocked-in {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .status-display.clocked-out {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .current-time {
            font-size: 48px;
            font-weight: bold;
            color: #333;
            font-family: 'Courier New', monospace;
            margin: 20px 0;
        }
        
        .hours-today {
            font-size: 24px;
            color: #6B4226;
            margin: 20px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-clock {
            padding: 15px 40px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn-clock-in {
            background-color: #2ecc71;
            color: white;
        }
        
        .btn-clock-in:hover {
            background-color: #27ae60;
        }
        
        .btn-clock-out {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-clock-out:hover {
            background-color: #c0392b;
        }
        
        .btn-clock:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .timesheet-list {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .timesheet-item {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .timesheet-item:last-child {
            border-bottom: none;
        }
        
        .time-label {
            font-weight: bold;
            color: #6B4226;
        }
        
        .time-value {
            color: #333;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #6B4226;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../navbar.php'; ?>

<div class="time-tracking-container">
    <h1>Time Tracking</h1>
    
    <div id="message" class="message"></div>
    
    <div class="time-status-card">
        <div class="status-display" id="statusDisplay">Loading...</div>
        <div class="current-time" id="currentTime">--:--:--</div>
        <div id="clockInTime" style="display: none;">
            Clocked in at: <span id="clockInTimeValue"></span>
        </div>
        <div class="hours-today">
            Today's Hours: <span id="hoursToday">0.00</span>h
        </div>
        
        <div class="action-buttons">
            <button id="btnClockIn" class="btn-clock btn-clock-in">Clock In</button>
            <button id="btnClockOut" class="btn-clock btn-clock-out" disabled>Clock Out</button>
        </div>
    </div>
    
    <div class="timesheet-list">
        <h2>Today's Time Log</h2>
        <div id="timesheetContent">
            <p style="text-align: center; color: #999;">No clocking records yet</p>
        </div>
    </div>
</div>

<script>
    function updateCurrentTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-PH', {
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
        document.getElementById('currentTime').textContent = timeStr;
    }
    
    function parsePhpDateTime(str) {
        if (!str) return null;
        const match = str.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/);
        if (match) {
            const year = parseInt(match[1]);
            const month = parseInt(match[2]) - 1;
            const day = parseInt(match[3]);
            const hour = parseInt(match[4]);
            const min = parseInt(match[5]);
            const sec = parseInt(match[6]);
            
        
            const utcDate = new Date(Date.UTC(year, month, day, hour - 8, min, sec));
            return utcDate;
        }
        return new Date(str);
    }
    
    async function loadTimesheet() {
        try {
            const response = await fetch('get_timesheet.php');
            const payload = await response.json();

            if (payload.success) {
                updateUI(payload.data);
            } else {
                showMessage('Error loading timesheet: ' + payload.error, 'error');
            }
        } catch (error) {
            showMessage('Error: ' + error.message, 'error');
        }
    }
    
    function updateUI(data) {
        const statusDisplay = document.getElementById('statusDisplay');
        const btnClockIn = document.getElementById('btnClockIn');
        const btnClockOut = document.getElementById('btnClockOut');
        const clockInTimeDiv = document.getElementById('clockInTime');
        const clockInTimeValue = document.getElementById('clockInTimeValue');
        const hoursToday = document.getElementById('hoursToday');
        
        hoursToday.textContent = (data.total_hours || 0).toFixed(2);

        if (data.open_shift) {
            statusDisplay.textContent = '✓ Currently Clocked In';
            statusDisplay.classList.add('clocked-in');
            statusDisplay.classList.remove('clocked-out');

            btnClockIn.disabled = true;
            btnClockOut.disabled = false;

            if (data.open_shift_clock_in) {
                const clockInDate = parsePhpDateTime(data.open_shift_clock_in);
                const clockInTime = clockInDate.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                clockInTimeValue.textContent = clockInTime;
                clockInTimeDiv.style.display = 'block';
            }
        } else {
            statusDisplay.textContent = '○ Clocked Out';
            statusDisplay.classList.add('clocked-out');
            statusDisplay.classList.remove('clocked-in');

            btnClockIn.disabled = false;
            btnClockOut.disabled = true;

            clockInTimeDiv.style.display = 'none';
        }

        const recs = data.records || [];
        if (recs.length === 0) {
            document.getElementById('timesheetContent').innerHTML = '<p style="text-align: center; color: #999;">No clocking records yet</p>';
        } else {
            let content = '';
            recs.forEach(r => {
                content += '<div class="timesheet-item">';
                content += '<div><span class="time-label">In:</span> <span class="time-value">' + (r.clock_in ? parsePhpDateTime(r.clock_in).toLocaleTimeString() : 'N/A') + '</span></div>';
                content += '<div><span class="time-label">Out:</span> <span class="time-value">' + (r.clock_out ? parsePhpDateTime(r.clock_out).toLocaleTimeString() : 'Still working') + '</span></div>';
                content += '<div><span class="time-label">Shift Hours:</span> <span class="time-value">' + ((r.hours_worked !== null) ? r.hours_worked.toFixed(2) + 'h' : '-') + '</span></div>';
                content += '</div>';
            });
            document.getElementById('timesheetContent').innerHTML = content;
        }
    }
    
    async function clockIn() {
        const btnClockIn = document.getElementById('btnClockIn');
        btnClockIn.disabled = true;
        btnClockIn.textContent = 'Clocking In...';
        
        try {
            const response = await fetch('clock_in.php', { method: 'POST' });
            const data = await response.json();
            
            if (data.success) {
                showMessage(data.message, 'success');
                loadTimesheet();
            } else {
                showMessage('Error: ' + data.error, 'error');
                btnClockIn.disabled = false;
                btnClockIn.textContent = 'Clock In';
            }
        } catch (error) {
            showMessage('Error: ' + error.message, 'error');
            btnClockIn.disabled = false;
            btnClockIn.textContent = 'Clock In';
        }
    }
    
    async function clockOut() {
        const btnClockOut = document.getElementById('btnClockOut');
        btnClockOut.disabled = true;
        btnClockOut.textContent = 'Clocking Out...';
        
        try {
            const response = await fetch('clock_out.php', { method: 'POST' });
            const data = await response.json();
            
            if (data.success) {
                showMessage('Clocked out. Hours worked today: ' + data.hours_worked, 'success');
                loadTimesheet();
            } else {
                showMessage('Error: ' + data.error, 'error');
                btnClockOut.disabled = false;
                btnClockOut.textContent = 'Clock Out';
            }
        } catch (error) {
            showMessage('Error: ' + error.message, 'error');
            btnClockOut.disabled = false;
            btnClockOut.textContent = 'Clock Out';
        }
    }
    
    function showMessage(msg, type) {
        const msgEl = document.getElementById('message');
        msgEl.textContent = msg;
        msgEl.className = 'message ' + type;
        setTimeout(() => {
            msgEl.className = 'message';
        }, 5000);
    }
    
    document.getElementById('btnClockIn').addEventListener('click', clockIn);
    document.getElementById('btnClockOut').addEventListener('click', clockOut);
    
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);
    loadTimesheet();
    setInterval(loadTimesheet, 10000);
</script>
</body>
</html>
