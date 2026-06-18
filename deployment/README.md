# Deployment Setup (Nginx & Supervisor)

This folder contains example configuration files for deploying the Laravel project (`accurate-customizer`) on a Linux server using **Nginx** and **Supervisor**.

## 1. Nginx Configuration

The `nginx.conf.example` file is an example block that routes traffic to the Laravel `public` directory.

### Steps to Apply Nginx Config:
1. Copy the `nginx.conf.example` file to your server's Nginx `sites-available` directory:
   ```bash
   sudo cp deployment/nginx.conf.example /etc/nginx/sites-available/accurate-customizer
   ```
2. Open the file and verify `customizer.zenith-dev.my.id`, `/var/www/accurate-customizer`, and `php8.4-fpm.sock` match your environment.
   ```bash
   sudo nano /etc/nginx/sites-available/accurate-customizer
   ```
3. Enable the site by creating a symlink to `sites-enabled`:
   ```bash
   sudo ln -s /etc/nginx/sites-available/accurate-customizer /etc/nginx/sites-enabled/
   ```
4. Test the Nginx configuration for syntax errors:
   ```bash
   sudo nginx -t
   ```
5. If the test passes, reload Nginx:
   ```bash
   sudo systemctl reload nginx
   ```

## 2. Supervisor Configuration

Since the project uses Laravel Queues (as seen by `QUEUE_CONNECTION` or queue jobs), Supervisor is used to keep the `queue:work` process running in the background.

### Steps to Apply Supervisor Config:
1. Make sure Supervisor is installed on your server:
   ```bash
   sudo apt-get install supervisor
   ```
2. Copy the `supervisor.conf.example` file to the supervisor configuration directory:
   ```bash
   sudo cp deployment/supervisor.conf.example /etc/supervisor/conf.d/accurate-customizer-queue.conf
   ```
3. Open the file and adjust `/var/www/accurate-customizer` to match your actual project path, and `user=www-data` to your actual server user if different (e.g., `ubuntu` or `root`).
   ```bash
   sudo nano /etc/supervisor/conf.d/accurate-customizer-queue.conf
   ```
4. Inform Supervisor about the new configuration and start the worker:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start accurate-customizer-queue:*
   ```
5. You can check the status of your queue workers at any time with:
   ```bash
   sudo supervisorctl status
   ```

## 3. Database Setup (MySQL)

This project uses MySQL. Ensure you configure your `.env` file properly.

### Steps to Setup Database:
1. Log into MySQL on your server:
   ```bash
   mysql -u root -p
   ```
2. Create the database:
   ```sql
   CREATE DATABASE `accurate-customizer`;
   ```
3. Update your `.env` file in `/var/www/accurate-customizer`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=accurate-customizer
   DB_USERNAME=root      # or your db user
   DB_PASSWORD=secret    # or your db password
   ```
4. Run the database migrations:
   ```bash
   cd /var/www/accurate-customizer
   php artisan migrate
   ```
