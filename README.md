# LUSTRO BOOKS 

LUSTRO BOOKS is a demo bookstore website I built as a portfolio project to practice web development with PHP and MySQL.

Users can browse books, add them to their cart or favorites, and place orders. They can also choose to become sellers when creating an account and publish their own books.

## Features

- Account registration with email verification
- Sign in and sign out
- Search for books and browse by category
- View book details and available quantities
- Add books to the cart or favorites
- Update cart quantities and place orders
- View previous orders
- Cancel pending orders within 24 hours
- Publish and remove books through a seller account
- Receive verification codes and order updates by email

## Technologies Used

- HTML
- CSS
- JavaScript
- PHP
- MySQL
- PHPMailer
- XAMPP

## Main Folders

- `image` — Images used on the website
- `icon` — Website icons
- `uploads` — Book images uploaded by sellers
- `vendor` — PHPMailer library files

## Running the Project Locally

1. Place the project inside the XAMPP `htdocs` folder with the name `lustro_books`.
2. Start Apache and MySQL.
3. Set up the `lustro_books` database with the required tables and sample data. The website does not create the database automatically.
4. Update `connection.php` if your database settings are different.
5. Add your own SMTP settings in `mail_config.php` to enable email verification and order emails.
6. Open `http://localhost/lustro_books/` in your browser.

> **Note:** `mail_config.php` is excluded from this repository because it contains private email configuration. You will need to create and configure your own version locally.

## Project Status

This project is still in development. Some parts need further testing and improvements, including order error handling, and form security.

I may improve these parts and add more features in the future.

This project is for learning and portfolio purposes. It does not handle real payments, deliveries, or seller payouts.

Email verification and order notifications can send real emails when the email service is configured.

## Screenshots

### Home
![LUSTRO BOOKS Home](Home.jpg)

### Browse Books
![Browse Books](Books.jpg)

### Book Details
![Book Details](Book_details.jpg)

### My Orders
![My Orders](Orders.jpg)

### Seller Dashboard
![Seller Dashboard](Seller_dashboard.jpg)
