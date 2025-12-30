/**
 * Profile Data Fetcher - Debug Version
 * Handles fetching and displaying user profile data with comprehensive error handling
 */

class ProfileDataFetcher {
    constructor() {
        this.apiEndpoint = 'api/profile_debug.php';
        this.loadingStates = {
            LOADING: 'loading',
            SUCCESS: 'success',
            ERROR: 'error'
        };
        this.currentStatus = this.loadingStates.LOADING;
    }

    /**
     * Main function to fetch and display profile data
     */
    async fetchAndDisplayProfileData() {
        console.log('🔄 Starting profile data fetch...');
        
        // Show loading state
        this.showLoadingState();
        
        try {
            // Fetch data from API
            const response = await fetch(this.apiEndpoint, {
                method: 'GET',
                credentials: 'same-origin', // Important for cookies/session
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            console.log('📡 API Response status:', response.status);
            console.log('📡 API Response headers:', [...response.headers.entries()]);

            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status} ${response.statusText}`);
            }

            // Parse JSON response
            const data = await response.json();
            console.log('📊 API Response data:', data);

            // Handle response based on status
            if (data.status === 'success') {
                this.displaySuccessState(data);
                this.currentStatus = this.loadingStates.SUCCESS;
            } else {
                throw new Error(data.message || 'Unknown API error');
            }

        } catch (error) {
            console.error('❌ Profile fetch error:', error);
            this.displayErrorState(error);
            this.currentStatus = this.loadingStates.ERROR;
        }
    }

    /**
     * Show loading state in UI
     */
    showLoadingState() {
        console.log('⏳ Showing loading state...');
        
        // Update all profile fields to show loading
        const loadingElements = [
            'userName', 'userEmail', 'memberSince', 'fullName', 
            'email', 'phoneNumber', 'studentId', 'reportsFiled', 
            'itemsRecovered', 'pendingClaims'
        ];
        
        loadingElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = 'Loading...';
                element.style.color = '#666';
                element.style.fontStyle = 'italic';
            }
        });

        // Update avatar
        const avatarTexts = document.querySelectorAll('.avatar-text');
        avatarTexts.forEach(el => {
            el.textContent = '...';
            el.parentElement.style.background = '#ccc';
        });

        // Update recent reports
        const recentReports = document.getElementById('recentReports');
        if (recentReports) {
            recentReports.innerHTML = '<p style="text-align: center; color: #666; font-style: italic;">Loading recent reports...</p>';
        }
    }

    /**
     * Display success state with actual data
     */
    displaySuccessState(data) {
        console.log('✅ Displaying success state with data:', data);
        
        // Reset styling
        this.resetElementStyles();

        // Update profile header
        this.updateElement('userName', data.user.fullName);
        this.updateElement('userEmail', data.user.email);
        this.updateElement('memberSince', `Member since ${data.user.memberSince}`);

        // Update personal information
        this.updateElement('fullName', data.user.fullName);
        this.updateElement('email', data.user.email);
        this.updateElement('phoneNumber', data.user.phone);
        this.updateElement('studentId', data.user.studentId);

        // Update activity statistics
        this.updateElement('reportsFiled', data.stats.reportsFiled);
        this.updateElement('itemsRecovered', data.stats.itemsRecovered);
        this.updateElement('pendingClaims', data.stats.pendingClaims);

        // Update avatar
        const avatarTexts = document.querySelectorAll('.avatar-text');
        avatarTexts.forEach(el => {
            el.textContent = data.user.avatar;
            el.parentElement.style.background = '#2563eb';
        });

        // Update recent reports
        const recentReports = document.getElementById('recentReports');
        if (recentReports) {
            if (data.recentReports && data.recentReports.length > 0) {
                recentReports.innerHTML = data.recentReports.map(report => `
                    <article class="report-item">
                        <div class="report-item__content">
                            <h3>${report.title}</h3>
                            <p class="item-meta">${report.type} • ${report.location} • ${report.date}</p>
                            <span class="status-badge ${this.getStatusClass(report.status)}">${report.status}</span>
                        </div>
                    </article>
                `).join('');
            } else {
                recentReports.innerHTML = '<p class="muted">No recent reports found.</p>';
            }
        }

        // Update user dropdown (if exists)
        this.updateUserDropdown(data.user);
        
        // Update about section (if exists)
        this.updateAboutSection(data);
    }

    /**
     * Display error state with helpful information
     */
    displayErrorState(error) {
        console.log('❌ Displaying error state:', error.message);
        
        // Update all profile fields to show error
        const errorElements = [
            'userName', 'userEmail', 'memberSince', 'fullName', 
            'email', 'phoneNumber', 'studentId', 'reportsFiled', 
            'itemsRecovered', 'pendingClaims'
        ];
        
        errorElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = 'Failed to load';
                element.style.color = '#dc2626';
                element.style.fontStyle = 'normal';
            }
        });

        // Update avatar with error state
        const avatarTexts = document.querySelectorAll('.avatar-text');
        avatarTexts.forEach(el => {
            el.textContent = '??';
            el.parentElement.style.background = '#dc2626';
        });

        // Show detailed error in recent reports section
        const recentReports = document.getElementById('recentReports');
        if (recentReports) {
            recentReports.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #dc2626;">
                    <h3>Error Loading Profile</h3>
                    <p><strong>${error.message}</strong></p>
                    <div style="margin: 1rem 0; padding: 1rem; background: #fef2f2; border-radius: 0.5rem; text-align: left;">
                        <h4>Troubleshooting Steps:</h4>
                        <ol style="margin: 0.5rem 0; padding-left: 1.5rem;">
                            <li>Make sure you are logged in</li>
                            <li>Check if MySQL is running in XAMPP</li>
                            <li>Try refreshing the page</li>
                            <li>Check browser console for detailed errors</li>
                        </ol>
                    </div>
                    <button onclick="profileFetcher.fetchAndDisplayProfileData()" class="btn btn-primary" style="margin-top: 1rem;">Retry</button>
                </div>
            `;
        }
    }

    /**
     * Helper method to update element content
     */
    updateElement(elementId, content) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = content;
            element.style.color = '';
            element.style.fontStyle = '';
        }
    }

    /**
     * Reset element styles to default
     */
    resetElementStyles() {
        const allElements = document.querySelectorAll('#profileHeader p, .info-item p, .stat-value');
        allElements.forEach(el => {
            el.style.color = '';
            el.style.fontStyle = '';
        });
    }

    /**
     * Get CSS class for status badge
     */
    getStatusClass(status) {
        switch (status) {
            case 'resolved': return 'success';
            case 'pending': return 'warning';
            case 'found': return 'info';
            default: return 'info';
        }
    }

    /**
     * Update user dropdown with user data
     */
    updateUserDropdown(user) {
        const dropdownNames = document.querySelectorAll('.dropdown-name');
        const dropdownEmails = document.querySelectorAll('.dropdown-email');
        
        dropdownNames.forEach(el => el.textContent = user.fullName);
        dropdownEmails.forEach(el => el.textContent = user.email);
    }

    /**
     * Update about section with user data
     */
    updateAboutSection(data) {
        // Update about section user statistics
        const userReportsCount = document.getElementById('userReportsCount');
        const userRecoveredCount = document.getElementById('userRecoveredCount');
        const userSuccessRate = document.getElementById('userSuccessRate');
        const aboutUserAvatar = document.getElementById('aboutUserAvatar');
        const aboutUserName = document.getElementById('aboutUserName');
        const aboutUserEmail = document.getElementById('aboutUserEmail');
        const aboutMemberSince = document.getElementById('aboutMemberSince');

        if (userReportsCount) userReportsCount.textContent = data.stats.reportsFiled;
        if (userRecoveredCount) userRecoveredCount.textContent = data.stats.itemsRecovered;
        
        // Calculate success rate
        const successRate = data.stats.reportsFiled > 0 
            ? Math.round((data.stats.itemsRecovered / data.stats.reportsFiled) * 100) 
            : 0;
        if (userSuccessRate) userSuccessRate.textContent = successRate + '%';

        if (aboutUserAvatar) aboutUserAvatar.textContent = data.user.avatar;
        if (aboutUserName) aboutUserName.textContent = data.user.fullName;
        if (aboutUserEmail) aboutUserEmail.textContent = data.user.email;
        if (aboutMemberSince) aboutMemberSince.textContent = `Member since ${data.user.memberSince}`;
    }

    /**
     * Get current status
     */
    getStatus() {
        return this.currentStatus;
    }
}

// Create global instance
const profileFetcher = new ProfileDataFetcher();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 DOM loaded, checking if profile page...');
    
    // Check if we're on the profile page
    if (document.body.dataset.requireAuthPage === 'true' || 
        window.location.pathname.includes('profile.html')) {
        console.log('📄 Profile page detected, fetching data...');
        profileFetcher.fetchAndDisplayProfileData();
    }
    
    // Also fetch for index.html about section
    if (window.location.pathname.includes('index.html') || window.location.pathname.endsWith('/')) {
        console.log('🏠 Index page detected, checking authentication...');
        // Check if user is authenticated before fetching
        fetch('api/debug.php')
            .then(response => response.json())
            .then(data => {
                if (data.data?.session?.isAuthenticated) {
                    console.log('✅ User authenticated, fetching profile for about section...');
                    profileFetcher.fetchAndDisplayProfileData();
                } else {
                    console.log('❌ User not authenticated, hiding user sections');
                }
            })
            .catch(error => {
                console.error('❌ Auth check failed:', error);
            });
    }
});

// Make it available globally for retry button
window.profileFetcher = profileFetcher;
