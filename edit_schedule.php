<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Misuki's Weekly Schedule Editor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        h1 {
            color: #764ba2;
            margin-bottom: 10px;
            font-size: 2.5em;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 25px;
            background: #f5f5f5;
            border: none;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            color: #666;
        }

        .tab:hover {
            background: #e0e0e0;
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .day-content {
            display: none;
        }

        .day-content.active {
            display: block;
        }

        .schedule-list {
            display: grid;
            gap: 15px;
        }

        .schedule-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #764ba2;
            display: grid;
            grid-template-columns: 80px 1fr 150px 100px 50px;
            gap: 15px;
            align-items: center;
            transition: all 0.3s;
        }

        .schedule-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .schedule-item input,
        .schedule-item select {
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .schedule-item input:focus,
        .schedule-item select:focus {
            outline: none;
            border-color: #764ba2;
        }

        .time-input {
            font-weight: 600;
            color: #764ba2;
        }

        .emoji-input {
            font-size: 20px;
            text-align: center;
        }

        .delete-btn {
            background: #ff4757;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .delete-btn:hover {
            background: #ff3838;
            transform: scale(1.05);
        }

        .add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3);
        }

        .save-all-btn {
            background: #2ecc71;
            color: white;
            border: none;
            padding: 20px 40px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            margin-top: 30px;
            width: 100%;
            transition: all 0.3s;
        }

        .save-all-btn:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3);
        }

        .success-message {
            background: #2ecc71;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            animation: slideIn 0.5s;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .type-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-personal { background: #ffb6c1; color: #8b0000; }
        .type-class { background: #ffd700; color: #8b4513; }
        .type-studying { background: #87ceeb; color: #00008b; }
        .type-commute { background: #98fb98; color: #006400; }
        .type-free { background: #dda0dd; color: #4b0082; }
        .type-sleep { background: #b0c4de; color: #191970; }
        .type-break { background: #f0e68c; color: #8b4513; }
        .type-university { background: #ffa07a; color: #8b0000; }
        .type-church { background: #e6e6fa; color: #483d8b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📅 Misuki's Weekly Schedule Editor</h1>
        <p class="subtitle">Edit Misuki's detailed weekly schedule. All times are in Saitama (Japan) timezone.</p>
        
        <div id="successMessage" class="success-message">
            ✅ Schedule saved successfully!
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showDay('monday')">Monday</button>
            <button class="tab" onclick="showDay('tuesday')">Tuesday</button>
            <button class="tab" onclick="showDay('wednesday')">Wednesday</button>
            <button class="tab" onclick="showDay('thursday')">Thursday</button>
            <button class="tab" onclick="showDay('friday')">Friday</button>
            <button class="tab" onclick="showDay('saturday')">Saturday</button>
            <button class="tab" onclick="showDay('sunday')">Sunday</button>
        </div>

        <div id="schedule-container"></div>

        <button class="save-all-btn" onclick="saveSchedule()">💾 Save All Changes</button>
    </div>

    <script>
        let scheduleData = <?php 
            require_once 'misuki_weekly_schedule.php';
            echo json_encode(getMisukiWeeklySchedule());
        ?>;

        const typeColors = {
            'personal': 'type-personal',
            'class': 'type-class',
            'studying': 'type-studying',
            'commute': 'type-commute',
            'free': 'type-free',
            'sleep': 'type-sleep',
            'break': 'type-break',
            'university': 'type-university',
            'church': 'type-church'
        };

        function showDay(day) {
            // Update tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');

            // Render schedule for this day
            renderSchedule(day);
        }

        function renderSchedule(day) {
            const container = document.getElementById('schedule-container');
            const daySchedule = scheduleData[day];

            let html = `
                <div class="day-content active">
                    <div class="schedule-list" id="${day}-schedule">
            `;

            daySchedule.forEach((item, index) => {
                html += `
                    <div class="schedule-item">
                        <input type="time" class="time-input" value="${item.time}" 
                               onchange="updateItem('${day}', ${index}, 'time', this.value)">
                        <input type="text" placeholder="Activity" value="${item.activity}"
                               onchange="updateItem('${day}', ${index}, 'activity', this.value)">
                        <input type="text" class="emoji-input" placeholder="😊" value="${item.emoji}"
                               onchange="updateItem('${day}', ${index}, 'emoji', this.value)">
                        <select onchange="updateItem('${day}', ${index}, 'type', this.value)">
                            <option value="personal" ${item.type === 'personal' ? 'selected' : ''}>Personal</option>
                            <option value="class" ${item.type === 'class' ? 'selected' : ''}>Class</option>
                            <option value="studying" ${item.type === 'studying' ? 'selected' : ''}>Studying</option>
                            <option value="commute" ${item.type === 'commute' ? 'selected' : ''}>Commute</option>
                            <option value="free" ${item.type === 'free' ? 'selected' : ''}>Free</option>
                            <option value="sleep" ${item.type === 'sleep' ? 'selected' : ''}>Sleep</option>
                            <option value="break" ${item.type === 'break' ? 'selected' : ''}>Break</option>
                            <option value="university" ${item.type === 'university' ? 'selected' : ''}>University</option>
                            <option value="church" ${item.type === 'church' ? 'selected' : ''}>Church</option>
                        </select>
                        <button class="delete-btn" onclick="deleteItem('${day}', ${index})">🗑️</button>
                    </div>
                `;
            });

            html += `
                    </div>
                    <button class="add-btn" onclick="addItem('${day}')">+ Add New Activity</button>
                </div>
            `;

            container.innerHTML = html;
        }

        function updateItem(day, index, field, value) {
            scheduleData[day][index][field] = value;
        }

        function deleteItem(day, index) {
            if (confirm('Are you sure you want to delete this activity?')) {
                scheduleData[day].splice(index, 1);
                renderSchedule(day);
            }
        }

        function addItem(day) {
            const newItem = {
                time: '12:00',
                activity: 'New Activity',
                emoji: '📌',
                type: 'free'
            };
            scheduleData[day].push(newItem);
            
            // Sort by time
            scheduleData[day].sort((a, b) => a.time.localeCompare(b.time));
            
            renderSchedule(day);
        }

        function saveSchedule() {
            // Send to server
            fetch('save_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(scheduleData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const msg = document.getElementById('successMessage');
                    msg.style.display = 'block';
                    setTimeout(() => {
                        msg.style.display = 'none';
                    }, 3000);
                }
            })
            .catch(error => {
                alert('Error saving schedule: ' + error);
            });
        }

        // Initial render
        renderSchedule('monday');
    </script>
</body>
</html>