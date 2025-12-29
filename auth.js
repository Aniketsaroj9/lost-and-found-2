document.addEventListener("DOMContentLoaded", () => {
    const AUTH_STORAGE_KEY = "lf:isAuthenticated";
    const API_BASE = "api";
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
    const storageIsRealLocalStorage = (() => {
        try {
            return storage === window.localStorage;
        } catch {
            return false;
        }
    })();
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const getRedirectTarget = () => {
        const params = new URLSearchParams(window.location.search);
        return params.get("redirect") || "dashboard.html";
    };

    const setStatus = (element, message, isError = false) => {
        if (!element) return;
        element.textContent = message;
        element.style.color = isError ? "#e11d48" : "var(--blue-600)";
    };

    const validateFields = (form) => {
        const status = form.querySelector(".form-status");
        const requiredInputs = form.querySelectorAll("input[required]");
        for (const input of requiredInputs) {
            const value = input.value.trim();
            if (!value) {
                setStatus(status, `${input.name || "Field"} is required.`, true);
                input.focus();
                return false;
            }
            if (input.type === "email" && !emailPattern.test(value)) {
                setStatus(status, "Please enter a valid email address.", true);
                input.focus();
                return false;
            }
            if (input.name === "password" && input.minLength && value.length < input.minLength) {
                setStatus(status, `Password must be at least ${input.minLength} characters.`, true);
                input.focus();
                return false;
            }
        }
        return true;
    };

    const loginForm = document.getElementById("loginFormAuth");
    if (loginForm) {
        loginForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            if (!validateFields(loginForm)) return;
            const statusEl = loginForm.querySelector(".form-status");
            setStatus(statusEl, "Checking credentials...");
            const formData = new FormData(loginForm);
            const payload = {
                email: formData.get("email"),
                password: formData.get("password"),
            };
            try {
                const response = await fetch(`${API_BASE}/login.php`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    credentials: "same-origin",
                    body: JSON.stringify(payload),
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || "Unable to log in.");
                }
                setStatus(statusEl, "Login successful. Redirecting...");
                storage.setItem(AUTH_STORAGE_KEY, "true");
                const redirectTarget = getRedirectTarget();
                const performRedirect = () => {
                    window.location.href = redirectTarget;
                };
                if (storageIsRealLocalStorage) {
                    performRedirect();
                } else {
                    setTimeout(performRedirect, 800);
                }
            } catch (error) {
                setStatus(statusEl, error.message, true);
            }
        });
    }

    const signupForm = document.getElementById("signupFormAuth");
    if (signupForm) {
        signupForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            if (!validateFields(signupForm)) return;
            const password = signupForm.querySelector("input[name='password']");
            const confirmPassword = signupForm.querySelector("input[name='confirmPassword']");
            if (password.value !== confirmPassword.value) {
                setStatus(signupForm.querySelector(".form-status"), "Passwords do not match.", true);
                confirmPassword.focus();
                return;
            }

            const statusEl = signupForm.querySelector(".form-status");
            setStatus(statusEl, "Creating your account...");
            const formData = new FormData(signupForm);
            const payload = {
                fullName: formData.get("fullName"),
                email: formData.get("email"),
                password: formData.get("password"),
            };

            try {
                const response = await fetch(`${API_BASE}/register.php`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    credentials: "same-origin",
                    body: JSON.stringify(payload),
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || "Unable to register.");
                }
                setStatus(statusEl, "Account created! Redirecting you...");
                storage.setItem(AUTH_STORAGE_KEY, "true");
                setTimeout(() => {
                    window.location.href = "dashboard.html";
                }, 600);
            } catch (error) {
                setStatus(statusEl, error.message, true);
            }
        });
    }

    if (storage.getItem(AUTH_STORAGE_KEY) === "true" && loginForm && storageIsRealLocalStorage) {
        window.location.href = getRedirectTarget();
    }
});
