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

    const navToggle = document.getElementById("navToggle");
    const navLinks = document.getElementById("navLinks");
    const yearEl = document.getElementById("year");

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
    const itemsGrid = document.getElementById("itemsGrid");
    const searchInput = document.getElementById("searchInput");
    const categoryFilter = document.getElementById("categoryFilter");

    const fetchItems = async () => {
        if (!itemsGrid) return;
        itemsGrid.innerHTML = '<p class="muted">Loading items...</p>';

        try {
            const params = new URLSearchParams();
            if (searchInput && searchInput.value.trim()) {
                params.append("search", searchInput.value.trim());
            }
            if (categoryFilter && categoryFilter.value !== "all") {
                params.append("category", categoryFilter.value);
            }

            const response = await fetch(`${API_BASE}/items.php?${params.toString()}`);
            if (!response.ok) throw new Error("Failed to load items");

            const result = await response.json();
            renderItems(result.data || []);
        } catch (error) {
            console.error(error);
            itemsGrid.innerHTML = '<p class="muted" style="color:var(--red-500)">Failed to load items. Please try refreshing.</p>';
        }
    };

    const renderItems = (items) => {
        if (!itemsGrid) return;
        if (!items.length) {
            itemsGrid.innerHTML = '<p class="muted">No items match your filters.</p>';
            return;
        }

        itemsGrid.innerHTML = items
            .map(
                (item) => `
                <article class="item-card">
                    <img src="${item.image_path ? item.image_path : 'https://placehold.co/600x400?text=No+Image'}" alt="${item.title}" loading="lazy" />
                    <div class="item-card__content">
                        <h3>${item.title}</h3>
                        <p class="item-meta">${item.category_name || 'Uncategorized'} • ${new Date(item.date).toLocaleDateString()}</p>
                        <p class="item-meta">Location: ${item.location}</p>
                    </div>
                </article>`
            )
            .join("");
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
});
