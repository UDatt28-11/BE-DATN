#!/bin/bash

# Script để fresh và seed lại toàn bộ database
# Usage: ./fresh-seed.sh hoặc bash fresh-seed.sh

echo "🔄 Starting fresh database migration and seeding..."
echo ""

# Kiểm tra xem có đang trong thư mục đúng không
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from the Laravel root directory."
    exit 1
fi

# Xác nhận từ người dùng
read -p "⚠️  This will DROP all tables and recreate them. Continue? (y/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Cancelled."
    exit 1
fi

echo ""
echo "📦 Step 1: Dropping all tables..."
php artisan migrate:fresh

echo ""
echo "🌱 Step 2: Seeding database..."
php artisan db:seed

echo ""
echo "✅ Done! Database has been fresh migrated and seeded."
echo ""
echo "📊 Default credentials:"
echo "   Admin: admin@staybook.com / password"
echo "   Owner: owner@staybook.com / password"
echo "   User:  user@staybook.com / password"



