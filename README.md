# Website Pencatatan Posyandu Balita Puskesmas Batu

## 🌟 Overview

Welcome to **Website Pencatatan Posyandu Balita**—a web application built to help manage the data of children’s health at Posyandu Balita (Integrated Health Post). This platform provides an easy-to-use interface for administrators to track children's growth, health measurements, and provide comprehensive reports.

---

## 🚀 Key Features

- **User Management:** Manage the admin users with the ability to add, update, or delete users.
- **Posyandu Management:** Create, edit, or delete data for different Posyandu locations.
- **Child Health Records:** Track children's health by adding their data, managing health checks, and recording weight and height.
- **Growth Measurements:** Record children’s height and weight regularly to monitor their growth.
- **Import/Export Data:** Import or export bulk child data and measurements using `.xlsx` files.
- **Responsive Design:** Access the website from desktop or mobile devices.

---

## 💻 Technologies Used

- **Frontend:** Vue.js (for building a dynamic user interface)
- **Backend:** Laravel (PHP framework for handling business logic)
- **Database:** MySQL (for relational data storage)
- **File Processing:** PhpSpreadsheet (for importing/exporting Excel files)

---

## ⚡ Quick Setup Guide

### 🔧 Prerequisites

Before setting up the project, ensure you have:

- PHP >= 7.4
- Composer
- Node.js & npm
- MySQL or MariaDB

### ⚙️ Installation Steps

1. **Clone the Repository:**

    ```bash
    git clone <repository_url>
    cd <project_directory>
    ```

2. **Install Backend Dependencies:**

    ```bash
    composer install
    ```

3. **Set Up Your Environment:**

    Copy `.env.example` to `.env` and configure the database settings:

    ```bash
    cp .env.example .env
    ```

4. **Generate Application Key:**

    ```bash
    php artisan key:generate
    ```

5. **Run Migrations:**

    Set up the database schema:

    ```bash
    php artisan migrate
    ```

6. **Install Frontend Dependencies:**

    ```bash
    npm install
    ```

7. **Run the Application:**

    Start the server:

    ```bash
    php artisan serve
    ```

    Now you can access the website on `http://127.0.0.1:8000`.

---

## 🔍 System Architecture

### **Model-View-Controller (MVC)**

- **Model:** Manages data and business logic (e.g., children’s health data, users).
- **View:** Vue.js-based frontend displaying data and receiving user input.
- **Controller:** Acts as a bridge, handling requests and interacting with the model to process data.

### **Frontend (Vue.js):**

- Build a responsive, single-page application that allows for real-time data interaction.
- Components such as forms, tables, and notifications for ease of use.

### **Backend (Laravel):**

- Handles business logic, such as user authentication, data validation, and file processing.
- Provides RESTful API endpoints for frontend communication.

### **Database (MySQL):**

- Stores user, Posyandu, child, and measurement data in relational tables.
- Uses foreign key relationships to ensure data integrity.

---

## 🛠️ Features Breakdown

### **User Management**

- Admin can add, edit, or delete user accounts with full control over the system.
  
### **Posyandu Management**

- Admin can manage health post locations by adding, editing, or removing data.

### **Child Data Management**

- Admin can add new children’s records, edit details, or delete outdated entries.

### **Growth Measurement Data**

- Record and monitor height, weight, and other health statistics of children over time.

### **Import/Export Data**

- **Import:** Bulk import data using `.xlsx` files.
- **Export:** Export child and measurement data for further analysis or backup.

---

## 🧑‍💻 Roles and Permissions

- **Admin Role:** Full control over the system, including managing users, Posyandu locations, children’s data, and measurements.

---

## 🔒 Security Features

- **Password Encryption:** Uses `bcrypt` to securely hash passwords.
- **HTTPS:** All communication between the frontend and backend is encrypted.
- **Input Validation:** Double-layered validation (client-side and server-side) to prevent invalid or malicious data entry.

---

## 🗓️ Future Roadmap

The current version is functional, but future updates will include:

- **Statistical Visualization:** Charts and graphs for visualizing children’s health progress.
- **Improved Import Mechanism:** Enhanced validation and error feedback.
- **Role Management:** Future support for multiple user roles with different permissions.
- **Reporting and Analysis:** Advanced reporting and statistical features for health monitoring.

---

## 🔧 Troubleshooting

If you run into issues, here are a few tips:

- Ensure your PHP and MySQL versions are correct.
- If the frontend is not loading, clear your browser cache or recompile assets:
  ```bash
  php artisan config:clear
  npm run dev

## 🌱 Contribute
Contributions are welcome! Feel free to fork the repository, make improvements, and submit pull requests. We welcome any enhancements, whether it’s new features, bug fixes, or improvements to documentation.

## ✨ Conclusion
The Website Pencatatan Posyandu Balita is a robust solution for managing and tracking children’s growth and health data at Posyandu Balita. It’s designed with simplicity and efficiency in mind, ensuring that health workers can focus on providing quality care while the system handles data entry and management.

## Example Code: Backend (Laravel)
### Routes
```
use App\Http\Controllers\ChildController;

Route::middleware(['auth'])->group(function() {
    Route::resource('children', ChildController::class);
});

Controller: ChildController
namespace App\Http\Controllers;

use App\Models\Child;
use Illuminate\Http\Request;

class ChildController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'birthdate' => 'required|date',
            'weight' => 'required|numeric',
            'height' => 'required|numeric',
        ]);

        $child = Child::create([
            'name' => $request->name,
            'birthdate' => $request->birthdate,
            'weight' => $request->weight,
            'height' => $request->height,
        ]);

        return redirect()->route('children.index')->with('success', 'Child data added successfully');
    }

    public function update(Request $request, $id)
    {
        $child = Child::findOrFail($id);
        
        $child->update($request->all());

        return redirect()->route('children.index')->with('success', 'Child data updated successfully');
    }
}```

### Frontend (Vue.js)
```
<template>
    <div class="add-child">
        <form @submit.prevent="addChild">
            <label for="name">Child's Name</label>
            <input v-model="name" type="text" id="name" required>

            <label for="birthdate">Birthdate</label>
            <input v-model="birthdate" type="date" id="birthdate" required>

            <label for="weight">Weight</label>
            <input v-model="weight" type="number" id="weight" required>

            <label for="height">Height</label>
            <input v-model="height" type="number" id="height" required>

            <button type="submit">Add Child</button>
        </form>
    </div>
</template>

<script>
export default {
    data() {
        return {
            name: '',
            birthdate: '',
            weight: '',
            height: ''
        };
    },
    methods: {
        async addChild() {
            try {
                await axios.post('/api/children', {
                    name: this.name,
                    birthdate: this.birthdate,
                    weight: this.weight,
                    height: this.height
                });

                this.$router.push({ name: 'children.index' });
            } catch (error) {
                console.error(error);
            }
        }
    }
};
</script>
```
