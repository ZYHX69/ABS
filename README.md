# Salon Appointment System – Elegance & Co.

A real‑time salon management system with **4 modules** (Clients, Appointments, Services, Public Bookings), **WebSocket CRUD**, **real‑time insights** (counters, activity feed, chart), **role‑based access** (admin/staff), **global search**, **pagination**, **audit logs**, and a **customer booking page** (no login required).

Built with **PHP** (Ratchet WebSocket), **MySQL**, **HTML/CSS/JS**, and **Chart.js**.

---

## 📋 Prerequisites

- **PHP** >= 7.4 with `pdo_mysql` extension enabled
- **MySQL** (e.g., XAMPP, WAMP, or standalone)
- **Composer** (for PHP dependencies)
- **Git** (optional, to clone the repository)

---

## 🚀 Installation & Running

### Option 1: One‑Click Batch Script (Windows)

If you have the `start-salon.bat` file in the project root, double‑click it.  
The script will:

- Create the `salon` database and import `db_dump.sql` (if it doesn’t exist).
- Start the WebSocket server (`php server.php`) in a new terminal window.
- Start the PHP built‑in web server on port 8000 in another terminal.
- Open your default browser to `http://localhost:8000/index.html`.

> **Note:** If you already have the database set up and want to skip the import, remove or comment out the MySQL lines in the batch file.

---

### Option 2: Manual Setup (All Platforms)

#### 1. Clone or download the project

```bash
git clone <your-repo-url>
cd salon-system