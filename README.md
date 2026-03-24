# Lost & Found Portal

A modern web-based platform designed for campuses and organizations to report, track, and recover lost items efficiently.

## 🚀 Features

- **User Authentication**: Secure Sign Up and Login system.
- **Report Items**: Easily report **Lost** or **Found** items with details and images.
- **Interactive Dashboard**: Track your personal reports and their status.
- **Search & Filter**: Browse reported items by category (Electronics, Documents, etc.) or keywords.
- **Responsive Design**: Works seamlessly on desktop and mobile devices.

## 🛠️ Tech Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript.
- **Backend**: Native PHP (No framework).
- **Database**: MySQL / MariaDB.
- **Local Server**: XAMPP (Apache).

## ⚙️ Installation & Setup

1.  **Clone the Repository**
    ```bash
    git clone https://github.com/Aniketsaroj9/lost-and-found-2.git
    cd lost-and-found-2
    ```

2.  **Setup Database**
    *   Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
    *   Create a new database named `lost_and_found_2`.
    *   Import the `database.sql` file provided in the root directory of this project.

3.  **Configure Project**
    *   Ensure the project folder `lost and found 2` is inside your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\lost and found 2`).
    *   Check `api/config.php` to ensure database credentials match your local setup (Default: User: `root`, Pass: ``).

4.  **Run the App**
    *   Start **Apache** and **MySQL** in XAMPP Control Panel.
    *   Open your browser and navigate to:
        `http://localhost/lost and found 2/`

## 📂 Project Structure

```
├── api/                # PHP backend endpoints
├── logs/               # Application error logs
├── uploads/            # User uploaded item images
├── css/                # Stylesheets (style.css, auth.css)
├── js/                 # Client-side scripts (script.js, auth.js)
├── index.html          # Landing Page
├── dashboard.html      # User Dashboard
├── login.html          # Login Page
├── signup.html         # Registration Page
├── lost.html           # Report Lost Item
├── found.html          # Report Found Item
├── database.sql        # Database Schema
└── README.md           # Project Documentation
```

## 🤝 Contributing

1.  Fork the repository.
2.  Create a new branch (`git checkout -b feature/YourFeature`).
3.  Commit your changes (`git commit -m 'Add some feature'`).
4.  Push to the branch (`git push origin feature/YourFeature`).
5.  Open a Pull Request.

## 📄 License

This project is open-source and available for educational purposes.

 ML Commands

 cd "C:\xampp\htdocs\lost and found 2\ml_service"

python app.py
