# The Village - Deployment Guide

## Overview

This guide covers the deployment of The Village P2P Trust Network across development, staging, and production environments.

## Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Backend       │    │   Database      │
│   (Dart/Wasm)   │◄──►│   (Laravel)     │◄──►│   (SQLite/MySQL)│
│   Port 8080     │    │   Port 8000     │    │   Port 3306     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │   Reverb        │
                    │   (WebSocket)   │
                    │   Port 8080     │
                    └─────────────────┘
```

## Environment Setup

### Prerequisites

- Docker & Docker Compose
- PHP 8.3+ 
- Node.js 18+
- Dart SDK 3.11+

### Environment Variables

#### Backend (.env)
```env
# Application
APP_NAME=TheVillage
APP_ENV=production
APP_DEBUG=false
APP_URL=https://thevillage.example.com

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=thevillage
DB_USERNAME=village_user
DB_PASSWORD=secure_password

# Reverb WebSocket
REVERB_APP_ID=village_app
REVERB_APP_KEY=reverb_key
REVERB_APP_SECRET=reverb_secret
REVERB_SCHEME=https
REVERB_HOST=thevillage.example.com
REVERB_PORT=8080
REVERB_SCALING_ENABLED=false

# Reputation System
INTEGRITY_DECAY_RATE=0.02
VOUCH_BONUS_RATE=0.20
RESOURCE_TRUST_WEIGHT=0.15
DOCUMENT_TRUST_WEIGHT=0.10
ASSET_TRUST_WEIGHT=0.05

# Security
BCRYPT_ROUNDS=12
SESSION_DRIVER=database
CACHE_DRIVER=database
QUEUE_CONNECTION=database

# Storage
FILESYSTEM_DISK=local
STORAGE_PATH=/var/www/storage
MAX_UPLOAD_SIZE=104857600
```

#### Frontend (pubspec.yaml)
```yaml
flutter:
  web:
    wasm: true
    renderer: skwasm
    source_map: false
```

## Docker Deployment

### Production Dockerfile

```dockerfile
# Backend Dockerfile
FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    zip \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    bcmath \
    opcache \
    pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader
RUN npm ci --production
RUN npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www/storage

# Expose port
EXPOSE 8000

# Start services
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### Docker Compose

```yaml
version: '3.8'

services:
  app:
    build:
      context: ./village_engine
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    environment:
      - APP_ENV=production
      - DB_HOST=mysql
    volumes:
      - ./storage:/var/www/storage
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: thevillage
      MYSQL_USER: village_user
      MYSQL_PASSWORD: secure_password
      MYSQL_ROOT_PASSWORD: root_password
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - app

  reverb:
    build:
      context: ./village_engine
      dockerfile: Dockerfile.reverb
    ports:
      - "8080:8080"
    environment:
      - REVERB_SCHEME=https
    depends_on:
      - mysql

volumes:
  mysql_data:
```

## Deployment Process

### 1. Preparation

```bash
# Clone repository
git clone https://github.com/your-org/thevillage.git
cd thevillage

# Set up environment
cp village_engine/.env.example village_engine/.env
# Edit .env with production values

# Build frontend
cd village_ui
flutter build web --wasm
cd ..
```

### 2. Database Setup

```bash
# Run migrations
docker-compose exec app php artisan migrate --force

# Seed database (optional)
docker-compose exec app php artisan db:seed --force

# Optimize database
docker-compose exec app php artisan db:optimize
```

### 3. Application Setup

```bash
# Install dependencies
docker-compose exec app composer install --no-dev --optimize-autoloader

# Clear caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

# Optimize for production
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
```

### 4. Start Services

```bash
# Start all services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f app
```

## Monitoring & Logging

### Application Logs

```bash
# View application logs
docker-compose logs -f app

# View Reverb logs
docker-compose logs -f reverb

# View nginx logs
docker-compose logs -f nginx
```

### Health Checks

```bash
# Check application health
curl https://thevillage.example.com/health

# Check WebSocket connection
curl https://thevillage.example.com:8080/health

# Database connectivity
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo()
```

### Performance Monitoring

```bash
# Application metrics
curl https://thevillage.example.com/api/metrics

# Database performance
docker-compose exec mysql mysql -uroot -p -e "SHOW PROCESSLIST;"

# Resource usage
docker stats
```

## Security Configuration

### SSL/TLS Setup

```nginx
# nginx.conf
server {
    listen 443 ssl http2;
    server_name thevillage.example.com;

    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;

    location / {
        proxy_pass http://app:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Firewall Rules

```bash
# Allow HTTP/HTTPS
ufw allow 80
ufw allow 443

# Allow WebSocket
ufw allow 8080

# Allow SSH (restrict to your IP)
ufw allow from YOUR_IP to any port 22
```

### Security Headers

```php
// Middleware/SecurityHeaders.php
public function handle($request, Closure $next)
{
    $response = $next($request);
    
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
    
    return $response;
}
```

## Scaling Configuration

### Horizontal Scaling

```yaml
# docker-compose.scale.yml
version: '3.8'

services:
  app:
    build: ./village_engine
    scale: 3
    environment:
      - CACHE_DRIVER=redis
      - QUEUE_CONNECTION=redis
    depends_on:
      - redis

  redis:
    image: redis:alpine
    ports:
      - "6379:6379"

  load_balancer:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./load_balancer.conf:/etc/nginx/nginx.conf
    depends_on:
      - app
```

### Load Balancer Configuration

```nginx
# load_balancer.conf
upstream app_servers {
    server app_1:8000;
    server app_2:8000;
    server app_3:8000;
}

server {
    listen 80;
    server_name thevillage.example.com;

    location / {
        proxy_pass http://app_servers;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

## Backup & Recovery

### Database Backup

```bash
# Create backup
docker-compose exec mysql mysqldump -u root -p thevillage > backup_$(date +%Y%m%d).sql

# Restore backup
docker-compose exec -T mysql mysql -u root -p thevillage < backup_20240101.sql
```

### File Backup

```bash
# Backup storage
tar -czf storage_backup_$(date +%Y%m%d).tar.gz storage/

# Restore storage
tar -xzf storage_backup_20240101.tar.gz
```

### Automated Backup Script

```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d)
BACKUP_DIR="/backups"

# Database backup
docker-compose exec mysql mysqldump -u root -p$MYSQL_ROOT_PASSWORD thevillage > $BACKUP_DIR/db_backup_$DATE.sql

# File backup
tar -czf $BACKUP_DIR/storage_backup_$DATE.tar.gz storage/

# Cleanup old backups (keep 7 days)
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

## Troubleshooting

### Common Issues

1. **WebSocket Connection Failed**
   - Check Reverb configuration
   - Verify firewall allows port 8080
   - Check SSL certificate validity

2. **Database Connection Error**
   - Verify database credentials
   - Check database service status
   - Ensure proper network connectivity

3. **High Memory Usage**
   - Check PHP memory limit
   - Optimize database queries
   - Implement caching strategies

4. **Slow Performance**
   - Enable OPcache
   - Use Redis for caching
   - Optimize database indexes

### Debug Commands

```bash
# Check PHP errors
docker-compose exec app tail -f /var/log/php_errors.log

# Check nginx errors
docker-compose exec nginx tail -f /var/log/nginx/error.log

# Database queries
docker-compose exec mysql mysql -uroot -p -e "SHOW FULL PROCESSLIST;"

# Memory usage
docker-compose exec app php -r "echo memory_get_usage(true) / 1024 / 1024 . ' MB\n';"
```

## Maintenance

### Regular Tasks

```bash
# Clear caches (weekly)
docker-compose exec app php artisan cache:clear

# Optimize database (monthly)
docker-compose exec mysql mysql -uroot -p -e "OPTIMIZE TABLE users, villages, reputation_scores;"

# Update dependencies (monthly)
docker-compose exec app composer update
docker-compose exec app npm update
```

### Health Monitoring

```bash
# Create health check script
cat > health_check.sh << 'EOF'
#!/bin/bash

# Check application
if curl -f https://thevillage.example.com/health > /dev/null 2>&1; then
    echo "✅ Application is healthy"
else
    echo "❌ Application is down"
    exit 1
fi

# Check database
if docker-compose exec mysql mysql -uroot -p -e "SELECT 1" > /dev/null 2>&1; then
    echo "✅ Database is healthy"
else
    echo "❌ Database is down"
    exit 1
fi

# Check WebSocket
if curl -f https://thevillage.example.com:8080/health > /dev/null 2>&1; then
    echo "✅ WebSocket is healthy"
else
    echo "❌ WebSocket is down"
    exit 1
fi
EOF

chmod +x health_check.sh
```

This deployment guide provides comprehensive instructions for deploying The Village across different environments with proper security, monitoring, and scaling considerations.
