// Prevent fallback script from running
window.journalLoaded = true;

document.addEventListener('DOMContentLoaded', () => {
    let foodEntries = [];
    const healthScore = document.getElementById('healthScore');
    const totalEntries = document.getElementById('totalEntries');
    const healthyStreak = document.getElementById('healthyStreak');
    const entriesList = document.getElementById('entriesList');
    const noEntriesMsg = document.getElementById('noEntries');
    const clearEntriesBtn = document.getElementById('clearEntries');
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    const insightsList = document.getElementById('insightsList');

    // Set initial loading state
    healthScore.textContent = '...';
    totalEntries.textContent = '...';
    healthyStreak.textContent = '...';

    // Tab functionality
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(`${tab.dataset.tab}Tab`).classList.add('active');
            if (tab.dataset.tab === "analytics") {
                initializeCharts();
            }
        });
    });

    // API Functions
    async function fetchEntries() {
        try {
            showLoading();
            const response = await fetch('journal_api.php?action=entries');
            const data = await response.json();
            
            if (data.success) {
                foodEntries = data.entries;
                await fetchStatistics(); // Fetch stats from server
                updateUI();
            } else {
                console.error('Failed to fetch entries:', data.error);
                showError('Failed to load food entries: ' + data.error);
                setErrorState();
            }
        } catch (error) {
            console.error('Error fetching entries:', error);
            showError('Failed to load food entries. Please try again.');
            setErrorState();
        } finally {
            hideLoading();
        }
    }

    async function fetchStatistics() {
        try {
            const response = await fetch('journal_api.php?action=stats');
            const data = await response.json();
            
            if (data.success) {
                updateStatistics(data.stats);
            } else {
                console.error('Failed to fetch statistics:', data.error);
                // Fallback to client-side calculation if server stats fail
                calculateStats();
            }
        } catch (error) {
            console.error('Error fetching statistics:', error);
            // Fallback to client-side calculation if server stats fail
            calculateStats();
        }
    }

    function setErrorState() {
        healthScore.textContent = '0%';
        totalEntries.textContent = '0';
        healthyStreak.textContent = '0';
    }

    function updateStatistics(stats) {
        healthScore.textContent = `${stats.healthScore}%`;
        totalEntries.textContent = stats.totalEntries;
        healthyStreak.textContent = stats.healthyStreak;
    }

    function isHealthyEntry(entry) {
        // Check if entry has Score A, B, or numeric score >= 80
        return entry.label.includes('Score A') || 
               entry.label.includes('Score B') || 
               (parseFloat(entry.score) >= 80);
    }

    async function saveEntry(entryData) {
        try {
            const response = await fetch('journal_api.php?action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(entryData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Add the new entry to our local array and refresh UI
                foodEntries.unshift(data.entry);
                await fetchStatistics(); // Refresh statistics from server
                updateUI();
                return true;
            } else {
                console.error('Failed to save entry:', data.error);
                showError('Failed to save entry: ' + data.error);
                return false;
            }
        } catch (error) {
            console.error('Error saving entry:', error);
            showError('Failed to save entry. Please try again.');
            return false;
        }
    }

async function clearAllEntries() {
    try {
        const response = await fetch('journal_api.php?action=clear', {
            method: 'POST', // Change from DELETE to POST
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();

        if (data.success) {
            foodEntries = [];
            await fetchStatistics(); // Refresh statistics from server
            updateUI();
            alert(`${data.deleted} entries cleared successfully.`);
        } else {
            console.error('Failed to clear entries:', data.error);
            showError('Failed to clear entries: ' + data.error);
        }
    } catch (error) {
        console.error('Error clearing entries:', error);
        showError('Failed to clear entries. Please try again.');
    }
}


    // UI Helper Functions
    function showLoading() {
        const loading = document.createElement('div');
        loading.id = 'loadingIndicator';
        loading.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 8px; z-index: 1000;';
        loading.textContent = 'Loading...';
        document.body.appendChild(loading);
    }

    function hideLoading() {
        const loading = document.getElementById('loadingIndicator');
        if (loading) {
            loading.remove();
        }
    }

    function showError(message) {
        alert(message); // You can replace this with a more sophisticated error display
    }

    function updateUI() {
        displayEntries();
        generateInsights();
    }

    function calculateStats() {
        if (foodEntries.length === 0) {
            healthScore.textContent = '0%';
            totalEntries.textContent = '0';
            healthyStreak.textContent = '0';
            return;
        }
        
        const healthCount = foodEntries.filter(entry => isHealthyEntry(entry)).length;
        const scorePercentage = Math.round((healthCount / foodEntries.length) * 100);

        const healthyDates = new Set();
        foodEntries.forEach(entry => {
            if (isHealthyEntry(entry)) {
                healthyDates.add(entry.date.split('T')[0]);
            }
        });

        healthScore.textContent = `${scorePercentage}%`;
        totalEntries.textContent = foodEntries.length;
        healthyStreak.textContent = healthyDates.size;
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function displayEntries() {
        if (foodEntries.length === 0) {
            noEntriesMsg.style.display = 'block';
            entriesList.innerHTML = '';
            return;
        }
        noEntriesMsg.style.display = 'none';
        entriesList.innerHTML = '';

        // Sort from newest to oldest
        const sortedEntries = [...foodEntries].sort((a, b) => new Date(b.date) - new Date(a.date));
        sortedEntries.forEach(entry => {
            const entryCard = document.createElement('div');
            entryCard.className = 'entry-card';

            const isHealthy = isHealthyEntry(entry);
            const healthClass = isHealthy ? 'healthy' : 'unhealthy';

            // Create a cleaner version of the advice text
            const formattedAdvice = formatAdvice(entry.advice);

            entryCard.innerHTML = `
                <img src="${entry.image}" alt="Food" class="entry-image">
                <div class="entry-content">
                    <div class="entry-title ${healthClass}">${entry.label}</div>
                    <div class="entry-date">${formatDate(entry.date)}</div>
                    <div class="entry-advice">${formattedAdvice}</div>
                </div>
            `;

            entriesList.appendChild(entryCard);
        });
    }
    
    function formatAdvice(advice) {
        // Format the advice text to be more readable
        if (!advice) return '';
        
        // Replace numbered points with bullet points
        let formatted = advice.replace(/\d+\.\s*\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        
        // Replace the existing formatting with cleaner HTML
        formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        
        // Split by points and create a cleaner list
        const points = formatted.split(/\d+\.\s*/).filter(point => point.trim());
        
        if (points.length > 1) {
            return points.map(point => `<p>${point.trim()}</p>`).join('');
        }
        
        return formatted;
    }
    
    function generateInsights() {
        if (foodEntries.length === 0) {
            insightsList.innerHTML = '<li>Start scanning foods to get personalized insights!</li>';
            return;
        }
        insightsList.innerHTML = '';

        const healthyCount = foodEntries.filter(entry => isHealthyEntry(entry)).length;
        const unhealthyCount = foodEntries.length - healthyCount;
        const healthyPercentage = Math.round((healthyCount / foodEntries.length) * 100);
        
        const entriesByDay = {};
        foodEntries.forEach(entry => {
            const day = new Date(entry.date).toLocaleDateString('en-US', {weekday: 'long'});
            if (!entriesByDay[day]) {
                entriesByDay[day] = [];
            }
            entriesByDay[day].push(entry);
        });

        let bestDay = null;
        let worstDay = null;
        let bestDayRatio = -1;
        let worstDayRatio = 101;

        for (const [day, entries] of Object.entries(entriesByDay)) {
            if (entries.length < 2) continue; // Skip days with too few entries

            const dayHealthyCount = entries.filter(entry => isHealthyEntry(entry)).length;
            const ratio = Math.round((dayHealthyCount / entries.length) * 100);

            if (ratio > bestDayRatio) {
                bestDayRatio = ratio;
                bestDay = day;
            }
            
            if (ratio < worstDayRatio) {
                worstDayRatio = ratio;
                worstDay = day;
            }
        }

        const insights = [];
        
        insights.push(`${healthyPercentage}% of your food choices are nutritious.`);
        
        if (healthyPercentage >= 70) {
            insights.push(`Excellent work! You're making predominantly healthy food choices.`);
        } else if (healthyPercentage >= 50) {
            insights.push(`Good progress! More than half of your food choices are nutritious.`);
        } else if (healthyPercentage <= 30) {
            insights.push(`Consider incorporating more nutritious options to boost your health score.`);
        }
        
        if (bestDay) {
            insights.push(`${bestDay} is your healthiest day with ${bestDayRatio}% nutritious choices.`);
        }
        
        if (worstDay) {
            insights.push(`${worstDay} shows opportunity for improvement with ${worstDayRatio}% nutritious choices.`);
        }

        // Add personalized recommendation
        if (foodEntries.length >= 5) {
            if (healthyPercentage < 50) {
                insights.push(`Try adding one more nutritious meal each day to significantly improve your overall health score.`);
            } else {
                insights.push(`Keep up the good work! Your consistent healthy choices are building a positive eating pattern.`);
            }
        }
        
        insights.forEach(insight => {
            const li = document.createElement('li');
            li.textContent = insight;
            insightsList.appendChild(li);
        });
    }

    function initializeCharts() {
        const healthyCount = foodEntries.filter(entry => isHealthyEntry(entry)).length;        
        const unhealthyCount = foodEntries.length - healthyCount;
        
        const ctxPie = document.getElementById('healthDistributionChart').getContext('2d');
        
        if (window.healthDistChart) {
            window.healthDistChart.destroy(); // Destroy existing chart if it exists
        }
        
        window.healthDistChart = new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: ['Nutritious', 'Less Nutritious'],
                datasets: [{
                    data: [healthyCount, unhealthyCount],
                    backgroundColor: ['#4CAF50', '#f44336'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        const lastDays = [];
        const today = new Date();
        
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(today.getDate() - i);
            lastDays.push(date);
        }

        const dailyData = lastDays.map(date => {
            const dateStr = date.toISOString().split('T')[0];
            const dayEntries = foodEntries.filter(entry => entry.date.split('T')[0] === dateStr);
            
            const dayHealthy = dayEntries.filter(entry => isHealthyEntry(entry)).length;
            const dayUnhealthy = dayEntries.length - dayHealthy;
        
            return {
                date: date.toLocaleDateString('en-US', {weekday: 'short'}),
                healthy: dayHealthy,
                unhealthy: dayUnhealthy
            };
        });
        
        const ctxBar = document.getElementById('weeklyTrackingChart').getContext('2d');
        if (window.dailyTrackingChart) {
            window.dailyTrackingChart.destroy();
        }

        window.dailyTrackingChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: dailyData.map(d => d.date),
                datasets: [
                    {
                        label: 'Nutritious',
                        data: dailyData.map(d => d.healthy),
                        backgroundColor: '#4CAF50',
                        borderWidth: 1
                    },
                    {
                        label: 'Less Nutritious',
                        data: dailyData.map(d => d.unhealthy),
                        backgroundColor: '#f44336',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Event Listeners
    clearEntriesBtn.addEventListener('click', () => {
        if (confirm('Are you sure you want to clear all food entries? This action cannot be undone.')) {
            clearAllEntries();
        }
    });

    // Expose saveEntry function globally so it can be called from other scripts
    window.saveEntryToJournal = saveEntry;
    
    // Initialize by fetching entries and statistics from database
    fetchEntries();
    
    // Initialize charts if analytics tab is active 
    if (document.querySelector('.tab[data-tab="analytics"]').classList.contains('active')) {
        initializeCharts();
    }
});