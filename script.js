document.addEventListener("DOMContentLoaded", () => {
    const AUTH_STORAGE_KEY = "lf:isAuthenticated";
    const PROTECTED_PAGE_SELECTOR = "[data-require-auth-page]";
    const API_BASE = "api";
    const SESSION_ENDPOINT = `${API_BASE}/session.php`;
    const LOGOUT_ENDPOINT = `${API_BASE}/logout.php`;

    const createStorage = () => {
        const tryStore = (getter) => {
            try {
                const store = getter();
                const testKey = "__lf_test__";
                store.setItem(testKey, "1");
                store.removeItem(testKey);
                return store;
            } catch {
                return null;
            }
        };

        const memoryStore = () => {
            const store = {};
            return {
                getItem: (key) => (Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null),
                setItem: (key, value) => {
                    store[key] = value;
                },
                removeItem: (key) => {
                    delete store[key];
                },
            };
        };

        return (
            tryStore(() => window.localStorage) ||
            tryStore(() => window.sessionStorage) ||
            memoryStore()
        );
    };

    const storage = createStorage();
    const storageSupportsEvents = (() => {
        try {
            return storage === window.localStorage;
        } catch {
            return false;
        }
    })();

    const isAuthenticated = () => storage.getItem(AUTH_STORAGE_KEY) === "true";

    const syncAuthVisibility = () => {
        const authed = isAuthenticated();
        document.querySelectorAll("[data-auth-visible]").forEach((el) => {
            const visibility = el.dataset.authVisible;
            const shouldHide = (visibility === "user" && !authed) || (visibility === "guest" && authed);
            el.classList.toggle("is-hidden", shouldHide);
        });
    };

    const redirectToLogin = (targetHref) => {
        const redirect = targetHref || "lost.html";
        window.location.href = `login.html?redirect=${encodeURIComponent(redirect)}`;
    };

    const enforceProtectedPage = () => {
        const protectedPage = document.querySelector(PROTECTED_PAGE_SELECTOR);
        if (!protectedPage) return;
        if (isAuthenticated()) return;
        const currentPath = window.location.pathname || "/index.html";
        redirectToLogin(currentPath);
    };

    const updateAuthUI = () => {
        syncAuthVisibility();
        enforceProtectedPage();
    };

    const applyAuthState = (authed) => {
        const current = isAuthenticated();
        if (current !== authed) {
            storage.setItem(AUTH_STORAGE_KEY, authed ? "true" : "false");
        }
        updateAuthUI();
    };

    const refreshAuthState = async () => {
        try {
            const response = await fetch(SESSION_ENDPOINT, { credentials: "same-origin" });
            if (!response.ok) return;
            const result = await response.json();
            applyAuthState(Boolean(result.authenticated));
        } catch (error) {
            console.warn("Unable to sync auth state", error);
        }
    };

    updateAuthUI();
    refreshAuthState();

    if (storageSupportsEvents) {
        window.addEventListener("storage", (event) => {
            if (event.key === AUTH_STORAGE_KEY) {
                updateAuthUI();
            }
        });
    }

    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
        logoutBtn.addEventListener("click", async () => {
            try {
                await fetch(LOGOUT_ENDPOINT, {
                    method: "POST",
                    credentials: "same-origin",
                });
            } catch (error) {
                console.warn("Logout request failed", error);
            } finally {
                storage.removeItem(AUTH_STORAGE_KEY);
                updateAuthUI();
                window.location.href = "index.html";
            }
        });
    }

    document.querySelectorAll("[data-require-auth-link]").forEach((link) => {
        link.addEventListener("click", (event) => {
            if (!isAuthenticated()) {
                event.preventDefault();
                const target = link.getAttribute("href") || "lost.html";
                alert("Please log in to access this feature.");
                redirectToLogin(target);
            }
        });
    });

    // Profile data fetching with proper error handling
    const fetchProfileData = async () => {
        try {
            const response = await fetch('api/profile.php', {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.status === 'success') {
                updateProfileUI(result);
                updateUserDropdown(result.user);
                updateUserAboutSection(result);
            } else {
                console.error('Profile data error:', result.message);
                showProfileError(result.message);
            }
        } catch (error) {
            console.error('Profile fetch error:', error);
            showProfileError('Failed to load profile data. Please check your internet connection and try again.');
        }
    };

    const showProfileError = (message) => {
        // Update all profile fields with error message
        const errorElements = [
            'userName', 'userEmail', 'memberSince', 'fullName',
            'email', 'phoneNumber', 'studentId', 'reportsFiled',
            'itemsRecovered', 'pendingClaims'
        ];

        errorElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = 'Failed to load';
                element.style.color = 'var(--red-600)';
            }
        });

        // Show error in recent reports section
        const recentReports = document.getElementById('recentReports');
        if (recentReports) {
            recentReports.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--red-600);">
                    <p><strong>Error:</strong> ${message}</p>
                    <button onclick="fetchProfileData()" class="btn btn-primary" style="margin-top: 1rem;">Retry</button>
                </div>
            `;
        }

        // Update avatar with error state
        const avatarTexts = document.querySelectorAll('.avatar-text');
        avatarTexts.forEach(el => {
            el.textContent = '??';
            el.parentElement.style.background = 'var(--red-600)';
        });
    };

    const updateProfileUI = (data) => {
        // Only update profile page elements if they exist
        if (document.getElementById('userName')) {
            // Reset error styling
            const allElements = document.querySelectorAll('#profileHeader p, .info-item p, .stat-value');
            allElements.forEach(el => {
                el.style.color = '';
            });

            // Update profile header
            const userAvatar = document.getElementById('userAvatar');
            const userName = document.getElementById('userName');
            const userEmail = document.getElementById('userEmail');
            const memberSince = document.getElementById('memberSince');

            if (userAvatar && data.user.avatar) {
                const avatarText = userAvatar.querySelector('.avatar-text');
                if (avatarText) {
                    avatarText.textContent = data.user.avatar;
                    userAvatar.parentElement.style.background = 'var(--blue-600)';
                }
            }
            if (userName) userName.textContent = data.user.fullName;
            if (userEmail) userEmail.textContent = data.user.email;
            if (memberSince) memberSince.textContent = `Member since ${data.user.memberSince}`;

            // Update personal information
            const fullName = document.getElementById('fullName');
            const email = document.getElementById('email');
            const phoneNumber = document.getElementById('phoneNumber');
            const studentId = document.getElementById('studentId');

            if (fullName) fullName.textContent = data.user.fullName;
            if (email) email.textContent = data.user.email;
            if (phoneNumber) phoneNumber.textContent = data.user.phone;
            if (studentId) studentId.textContent = data.user.studentId;

            // Update activity statistics
            const reportsFiled = document.getElementById('reportsFiled');
            const itemsRecovered = document.getElementById('itemsRecovered');
            const pendingClaims = document.getElementById('pendingClaims');

            if (reportsFiled) reportsFiled.textContent = data.stats.reportsFiled;
            if (itemsRecovered) itemsRecovered.textContent = data.stats.itemsRecovered;
            if (pendingClaims) pendingClaims.textContent = data.stats.pendingClaims;

            // Update recent reports
            const recentReports = document.getElementById('recentReports');
            if (recentReports) {
                if (data.recentReports && data.recentReports.length > 0) {
                    recentReports.innerHTML = data.recentReports.map(report => `
                        <article class="report-item">
                            <div class="report-item__content">
                                <h3>${report.title}</h3>
                                <p class="item-meta">${report.type} • ${report.location} • ${report.date}</p>
                                <span class="status-badge ${report.status === 'resolved' ? 'success' : report.status === 'pending' ? 'warning' : 'info'}">${report.status}</span>
                            </div>
                        </article>
                    `).join('');
                } else {
                    recentReports.innerHTML = '<p class="muted">No recent reports found.</p>';
                }
            }
        }

        // Always update about section and dropdown if they exist
        updateUserAboutSection(data);
        updateUserDropdown(data.user);
    };

    const updateUserDropdown = (user) => {
        // Update all dropdown user info across pages
        const dropdownNames = document.querySelectorAll('.dropdown-name');
        const dropdownEmails = document.querySelectorAll('.dropdown-email');
        const avatarTexts = document.querySelectorAll('.avatar-text');

        dropdownNames.forEach(el => el.textContent = user.fullName);
        dropdownEmails.forEach(el => el.textContent = user.email);
        avatarTexts.forEach(el => {
            el.textContent = user.avatar;
            el.parentElement.style.background = 'var(--blue-600)';
        });
    };

    const updateUserAboutSection = (data) => {
        // Update about section user information
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
    };

    // Verify authentication and fetch profile data
    refreshAuthState().then(() => {
        if (isAuthenticated()) {
            console.log('User is authenticated, fetching profile data...');
            fetchProfileData();
        } else {
            console.log('User is not authenticated');
            // User sections are already handled by syncAuthVisibility, but strictly ensuring:
            const userSections = document.querySelectorAll('[data-auth-visible="user"]');
            userSections.forEach(section => {
                section.classList.add('is-hidden');
            });
        }
    });

    // Debug: Check authentication status
    console.log('Authentication status:', isAuthenticated());
    console.log('Current page:', window.location.pathname);

    // Edit profile functionality
    const editProfileBtn = document.getElementById('editProfileBtn');
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', () => {
            // Toggle edit mode for profile fields
            const infoItems = document.querySelectorAll('.info-item p');
            const isEditing = editProfileBtn.textContent === 'Save Profile';

            if (isEditing) {
                // Save changes
                editProfileBtn.textContent = 'Saving...';
                editProfileBtn.disabled = true;

                const payload = {
                    fullName: document.getElementById('fullName').textContent.trim(),
                    phone: document.getElementById('phoneNumber').textContent.trim(),
                    studentId: document.getElementById('studentId').textContent.trim()
                };

                fetch('api/update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Success update
                            editProfileBtn.textContent = 'Edit Profile';
                            editProfileBtn.classList.remove('btn-success');
                            editProfileBtn.classList.add('btn-primary');
                            infoItems.forEach(item => {
                                item.contentEditable = false;
                                item.style.border = 'none';
                                item.style.background = 'transparent';
                            });

                            // Update header name too
                            const linkName = document.getElementById('userName');
                            if (linkName) linkName.textContent = payload.fullName;
                            updateUserDropdown({ fullName: payload.fullName, email: document.getElementById('email').textContent, avatar: payload.fullName.substring(0, 2).toUpperCase() });

                        } else {
                            alert('Error saving profile: ' + data.message);
                            editProfileBtn.textContent = 'Save Profile';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Failed to save profile. Check console.');
                        editProfileBtn.textContent = 'Save Profile';
                    })
                    .finally(() => {
                        editProfileBtn.disabled = false;
                    });

            } else {
                // Enable editing
                editProfileBtn.textContent = 'Save Profile';
                editProfileBtn.classList.remove('btn-primary');
                editProfileBtn.classList.add('btn-success');
                infoItems.forEach(item => {
                    item.contentEditable = true;
                    item.style.border = '1px solid var(--blue-300)';
                    item.style.background = 'var(--gray-50)';
                    item.style.padding = '0.5rem';
                    item.style.borderRadius = 'var(--radius-sm)';
                });
            }
        });
    }

    const navToggle = document.getElementById("navToggle");
    const navLinks = document.getElementById("navLinks");
    const yearEl = document.getElementById("year");

    // User dropdown functionality
    const userMenuTrigger = document.getElementById("userMenuTrigger");
    const userDropdown = document.getElementById("userDropdown");

    const toggleUserDropdown = (event) => {
        event.preventDefault(); // Prevent default button behavior
        event.stopPropagation();
        userDropdown.classList.toggle("show");
        console.log("Dropdown toggled. Classes:", userDropdown.classList);
    };

    const closeUserDropdown = (event) => {
        if (userMenuTrigger && userDropdown) {
            if (!userMenuTrigger.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove("show");
            }
        }
    };

    if (userMenuTrigger && userDropdown) {
        console.log("Adding dropdown event listeners");
        userMenuTrigger.addEventListener("click", toggleUserDropdown);
        document.addEventListener("click", closeUserDropdown);
    } else {
        console.error("Dropdown elements not found:", { userMenuTrigger, userDropdown });
    }

    if (yearEl) {
        yearEl.textContent = new Date().getFullYear();
    }

    if (navToggle && navLinks) {
        navToggle.addEventListener("click", () => {
            navLinks.classList.toggle("open");
        });

        navLinks.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => navLinks.classList.remove("open"));
        });
    }

    // No more dummy items
    const lostItemsGrid = document.getElementById("lostItemsGrid");
    const foundItemsGrid = document.getElementById("foundItemsGrid");
    const searchInput = document.getElementById("searchInput");
    const categoryFilter = document.getElementById("categoryFilter");

    const fetchItems = async () => {
        console.log("fetchItems called");
        if (lostItemsGrid) lostItemsGrid.innerHTML = '<p class="muted">Loading items...</p>';
        if (foundItemsGrid) foundItemsGrid.innerHTML = '<p class="muted">Loading items...</p>';

        try {
            const params = new URLSearchParams();
            if (searchInput && searchInput.value.trim()) {
                params.append("search", searchInput.value.trim());
            }
            if (categoryFilter && categoryFilter.value !== "all") {
                params.append("category", categoryFilter.value);
            }

            console.log("Fetching from:", `${API_BASE}/items.php?${params.toString()}`);
            const response = await fetch(`${API_BASE}/items.php?${params.toString()}`);
            console.log("Response status:", response.status);

            if (!response.ok) throw new Error("Failed to load items: " + response.statusText);

            const result = await response.json();
            console.log("Data received:", result);
            renderItems(result.data || []);
        } catch (error) {
            console.error("Fetch error:", error);
            const errorMsg = `<p class="muted" style="color:var(--red-500)">Failed to load items: ${error.message}</p>`;
            if (lostItemsGrid) lostItemsGrid.innerHTML = errorMsg;
            if (foundItemsGrid) foundItemsGrid.innerHTML = errorMsg;
        }
    };

    const renderItems = (items) => {
        const createItemCard = (item) => `
                <article class="item-card">
                    <img src="${item.image_path ? item.image_path : 'https://placehold.co/600x400?text=No+Image'}" alt="${item.title}" loading="lazy" />
                    <div class="item-card__content">
                        <h3>${item.title}</h3>
                        <p class="item-meta">${item.category_name || 'Uncategorized'} • ${new Date(item.date).toLocaleDateString()}</p>
                        <p class="item-meta">Location: ${item.location}</p>
                    </div>
                </article>`;

        const lostItems = items.filter(item => item.item_type && item.item_type.toLowerCase() === 'lost');
        const foundItems = items.filter(item => item.item_type && item.item_type.toLowerCase() === 'found');

        if (lostItemsGrid) {
            if (lostItems.length === 0) {
                lostItemsGrid.innerHTML = '<p class="muted">No lost items match your filters.</p>';
            } else {
                lostItemsGrid.innerHTML = lostItems.map(createItemCard).join("");
            }
        }

        if (foundItemsGrid) {
            if (foundItems.length === 0) {
                foundItemsGrid.innerHTML = '<p class="muted">No found items match your filters.</p>';
            } else {
                foundItemsGrid.innerHTML = foundItems.map(createItemCard).join("");
            }
        }
    };

    // Use debouncing for search if needed, currently direct for simplicity
    const applyFilters = () => {
        fetchItems();
    };

    fetchItems();

    if (searchInput) {
        searchInput.addEventListener("input", applyFilters);
    }

    if (categoryFilter) {
        categoryFilter.addEventListener("change", applyFilters);
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const setStatus = (element, message, isError = false) => {
        if (!element) return;
        element.textContent = message;
        element.style.color = isError ? "#e11d48" : "var(--blue-600)";
    };

    const attachFormHandler = (form, options = {}) => {
        if (!form) return;
        const statusEl = form.querySelector(".form-status");
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            const requiredFields = form.querySelectorAll("input[required], textarea[required], select[required]");
            const errors = [];

            requiredFields.forEach((field) => {
                const value = field.value.trim();
                if (!value) {
                    errors.push(`${field.name || "Field"} is required.`);
                    return;
                }
                if (field.type === "email" && !emailPattern.test(value)) {
                    errors.push("Enter a valid email address.");
                }
                if (field.name === "password" && field.minLength && value.length < field.minLength) {
                    errors.push(`Password must be at least ${field.minLength} characters.`);
                }
            });

            if (typeof options.validate === "function") {
                const extraError = options.validate(form);
                if (extraError) {
                    errors.push(extraError);
                }
            }

            if (errors.length) {
                setStatus(statusEl, errors[0], true);
            } else {
                setStatus(statusEl, options.successMessage || "Form submitted successfully.");
                form.reset();
            }
        });
    };

    const submitItemReport = async (form, itemType) => {
        const statusEl = form.querySelector(".form-status");
        if (!statusEl) return;

        setStatus(statusEl, "Submitting report...");

        try {
            const formData = new FormData(form);
            formData.append("type", itemType);

            const response = await fetch(`${API_BASE}/items.php`, {
                method: "POST",
                body: formData,
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || "Failed to submit report.");
            }

            setStatus(statusEl, result.message || "Report submitted successfully!", false);
            form.reset();

            // Redirect to dashboard after success
            setTimeout(() => {
                window.location.href = "dashboard.html";
            }, 1500);

        } catch (error) {
            setStatus(statusEl, error.message, true);
        }
    };

    const lostForm = document.getElementById("lostForm");
    if (lostForm) {
        lostForm.addEventListener("submit", (e) => {
            e.preventDefault();
            submitItemReport(lostForm, "lost");
        });
    }

    const foundForm = document.getElementById("foundForm");
    if (foundForm) {
        foundForm.addEventListener("submit", (e) => {
            e.preventDefault();
            submitItemReport(foundForm, "found");
        });
    }

    // Login/Signup handled by auth.js on dedicated pages
    // Keeping these here just in case they are ever added to other pages, but typically unused in this architecture
    const inlineLoginForm = document.getElementById("loginForm");
    if (inlineLoginForm) {
        attachFormHandler(inlineLoginForm, { successMessage: "Logged in." });
    }

    const inlineSignupForm = document.getElementById("signupForm");
    if (inlineSignupForm) {
        attachFormHandler(inlineSignupForm, { successMessage: "Account created." });
    }

    const setupTabs = () => {
        const tabs = document.querySelectorAll(".tab-btn");
        const sections = {
            lost: document.getElementById("lostSection"),
            found: document.getElementById("foundSection")
        };

        tabs.forEach(tab => {
            tab.addEventListener("click", () => {
                const target = tab.dataset.tab;

                // Update buttons
                tabs.forEach(t => t.classList.remove("active"));
                tab.classList.add("active");

                // Update sections
                Object.keys(sections).forEach(key => {
                    const section = sections[key];
                    if (section) {
                        if (key === target) {
                            section.classList.remove("hidden");
                        } else {
                            section.classList.add("hidden");
                        }
                    }
                });
            });
        });
    };

    setupTabs();
});
