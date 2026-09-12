<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\RestaurantGalleryImage;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds the one active restaurant this launch supports - House of Ramen -
 * with its real menu (transcribed from the restaurant's own printed menu
 * boards, see database/seed-data/restaurant) and real photos. No invented
 * business information: opening hours and social links are left null
 * until the restaurant provides them (see App\Models\Restaurant and the
 * public site's "coming soon" placeholders for those fields).
 */
class RestaurantSeeder extends Seeder
{
    private const SEED_PATH = 'seed-data/restaurant';

    public function run(): void
    {
        $restaurant = Restaurant::updateOrCreate(
            ['slug' => 'house-of-ramen'],
            [
                'name' => 'House of Ramen',
                'tagline' => 'Modern ramen & Japanese-Korean comfort food',
                'description' => 'House of Ramen brings together Japanese and Korean comfort food - hand-cut ramen noodles, rich broths, sushi rolls, bento boxes, and more - in a warm, modern space in Uttara, Dhaka.',
                'logo_path' => $this->storeSeedImage('logo-icon.png', 'restaurant/branding'),
                'cover_image_path' => $this->storeSeedImage('interior-1.jpg', 'restaurant/branding'),
                'phone' => '+8801742-152198',
                'email' => null,
                'address' => '21, Road 10/a, Gareeb-e-Newaz Avenue, Sector 11',
                'area' => 'Uttara, Dhaka',
                'opening_hours' => null,
                'facebook_url' => null,
                'instagram_url' => null,
                'delivery_platforms' => ['Foodi', 'foodpanda'],
                'is_active' => true,
                'created_by' => null,
                'updated_by' => null,
            ]
        );

        $this->seedGallery($restaurant);
        $this->seedMenu($restaurant);
    }

    private function seedGallery(Restaurant $restaurant): void
    {
        $images = [
            ['file' => 'interior-1.jpg', 'category' => 'interior', 'caption' => 'Dining area'],
            ['file' => 'food/tonkatsu-ramen.jpg', 'category' => 'food', 'caption' => 'Tonkatsu Ramen'],
            ['file' => 'food/gyukotsu-ramen.jpg', 'category' => 'food', 'caption' => 'Gyukotsu Ramen'],
            ['file' => 'food/budae-jjigae-seafood.jpg', 'category' => 'food', 'caption' => 'Seafood Budae Jjigae'],
            ['file' => 'food/budae-jjigae-chicken.jpg', 'category' => 'food', 'caption' => 'Chicken Budae Jjigae'],
            ['file' => 'food/nasi-goreng.jpg', 'category' => 'food', 'caption' => 'Nasi Goreng'],
            ['file' => 'food/seafood-bento.jpg', 'category' => 'food', 'caption' => 'Seafood Bento'],
            ['file' => 'food/spread.jpg', 'category' => 'food', 'caption' => 'Ramen & sushi spread'],
        ];

        foreach ($images as $order => $image) {
            RestaurantGalleryImage::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'caption' => $image['caption']],
                [
                    'category' => $image['category'],
                    'path' => $this->storeSeedImage($image['file'], 'restaurant/gallery'),
                    'display_order' => $order,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedMenu(Restaurant $restaurant): void
    {
        // Transcribed verbatim (name, description, price in BDT) from the
        // restaurant's own printed menu boards - see
        // database/seed-data/restaurant. `image` keys point at real food
        // photography where available; every other item shows the public
        // site's neutral placeholder rather than a stock photo.
        $categories = [
            [
                'name' => 'Appetizers', 'description' => null,
                'items' => [
                    ['name' => 'Heamul Pajeon', 'description' => 'Korean Seafood Pancake', 'price' => 450],
                    ['name' => 'Prawn Tempura', 'price' => 560],
                    ['name' => 'Crispy Mushroom Tempura', 'description' => 'Served with sweet chili mayo', 'price' => 390],
                    ['name' => 'Corn Seaweed Tempura Poppers', 'price' => 270],
                    ['name' => 'Chicken Wings', 'description' => 'Portion: 4 pieces. Crispy & Korean', 'price' => 210, 'price_note' => 'Korean style: ৳390'],
                    ['name' => 'Nanban', 'description' => 'Boneless fried chicken with Japanese tartar sauce', 'price' => 450],
                    ['name' => 'Thai Fried Chicken', 'description' => 'Served with Thai chili aioli. Portion: 6 pieces', 'price' => 420],
                    ['name' => 'Stir Fried Snow Fungus', 'description' => 'Served with Thai chili aioli. Portion: 6 pieces', 'price' => 450],
                ],
            ],
            [
                'name' => 'Dim Sum', 'description' => null,
                'items' => [
                    ['name' => 'Gyoza', 'price' => 290],
                    ['name' => 'Chilli Oil Wonton', 'price' => 350],
                    ['name' => 'Steamed Dumplings', 'price' => 260],
                ],
            ],
            [
                'name' => 'Noodles', 'description' => null,
                'items' => [
                    ['name' => 'Japchae', 'description' => 'Korean glass noodles with shiitake mushroom, black fungus, chicken, egg and vegetables', 'price' => 550],
                    ['name' => 'Beef Soba Carbonara', 'description' => 'Japanese buckwheat noodle with creamy carbonara sauce and beef bacon', 'price' => 590],
                    ['name' => 'Creamy Tobiko Udon', 'description' => 'Udon noodle with creamy tobiko sauce and butter poached chicken', 'price' => 550],
                    ['name' => 'Mee Goreng Mamak', 'description' => 'Spicy noodle with chicken, tofu puff, bok choy, tomato and fried egg', 'price' => 480],
                    ['name' => 'Dan Dan Noodle', 'description' => 'Noodles served with creamy peanut sauce, minced chicken, fried scallion, chili oil and sesame seeds', 'price' => 450],
                    ['name' => 'Seafood Hakka', 'description' => 'Stir fried noodles with squid, octopus, dory fish, clams and crispy prawn', 'price' => 450],
                    ['name' => 'Hakka Noodle', 'description' => 'Stir fried noodles with vegetables and egg', 'price' => 230],
                    ['name' => 'Chicken Chow Mein', 'price' => 390],
                ],
            ],
            [
                'name' => 'Budae Jjigae', 'description' => 'Korean army stew',
                'items' => [
                    ['name' => 'Chicken Budae Jjigae', 'description' => 'Rich gochujang broth, pan fried chicken, chicken ball, sausage, tofu puff, soft boiled egg, sweet corn, spring onion & sesame seeds', 'price' => 1150, 'image' => 'food/budae-jjigae-chicken.jpg'],
                    ['name' => 'Seafood Budae Jjigae', 'description' => 'Rich gochujang broth, prawns, squid, octopus, dory fish, crab stick, mussels, seafood tofu, soft boiled egg, sweet corn, spring onion & sesame seeds', 'price' => 1550, 'image' => 'food/budae-jjigae-seafood.jpg', 'featured' => true],
                    ['name' => 'Beef Budae Jjigae', 'description' => 'Rich gochujang broth, beef bulgogi, beef bacon, black fungus, shiitake mushroom, bean sprout, soft boiled egg, sweet corn, spring onion & sesame seeds', 'price' => 1650],
                ],
            ],
            [
                'name' => 'Japanese Ramen', 'description' => 'Hand cut noodles',
                'items' => [
                    ['name' => 'Beef Shoyu Ramen', 'description' => 'Soy sauce flavored beef broth, tender beef, shiitake mushroom, black fungus, egg', 'price' => 550],
                    ['name' => 'Miso Chicken Ramen', 'description' => 'Japanese miso flavored broth, chicken chashu, butter poached chicken, egg, corn, wood ear mushroom', 'price' => 490],
                    ['name' => 'Tonkatsu Ramen', 'description' => 'Creamy chicken broth, chicken chashu, chicken ball, corn, egg', 'price' => 490, 'image' => 'food/tonkatsu-ramen.jpg', 'featured' => true],
                    ['name' => 'Tantan Ramen', 'description' => 'Peanut flavored creamy broth, minced chicken, chicken ball, corn, egg', 'price' => 490],
                    ['name' => 'Gyukotsu Ramen', 'description' => 'Rich white beef broth, pan seared beef with shiitake mushroom, black fungus, egg', 'price' => 590, 'image' => 'food/gyukotsu-ramen.jpg', 'featured' => true],
                ],
            ],
            [
                'name' => 'Korean Ramen', 'description' => null,
                'items' => [
                    ['name' => 'Hot Korean Chicken Ramen', 'description' => 'Gochujang flavored broth, pan fried chicken breast, chicken sausage, chicken ball, corn, soft boiled egg', 'price' => 420],
                    ['name' => 'Kimchi Ramen', 'description' => 'Kimchi broth, pan fried chicken breast, chicken sausage, chicken ball, corn, soft boiled egg', 'price' => 490],
                    ['name' => 'Seafood Ramen', 'description' => 'Gochujang flavored broth, stir fried squid, octopus, dory fish, crab stick, prawn, soft boiled egg', 'price' => 590],
                    ['name' => 'Beef Bulgogi Ramen', 'description' => 'Gochujang flavored broth, pan seared beef, shiitake mushrooms, corn, soft boiled egg', 'price' => 590],
                    ['name' => 'Dumpling Ramen', 'description' => 'Gochujang flavored broth, chicken, dumpling, corn, soft boiled egg', 'price' => 390],
                    ['name' => 'Spicy Creamy Chicken Ramen', 'description' => 'Milk based spicy broth, tamagoyaki, chicken, chicken ball, corn, spring onion', 'price' => 490],
                ],
            ],
            [
                'name' => 'Maki Sushi', 'description' => null,
                'items' => [
                    ['name' => 'Spicy Tuna Mayo Maki Roll', 'price' => 380, 'price_note' => '8pcs: ৳690'],
                    ['name' => 'Sesame Crusted Tuna Roll', 'price' => 390, 'price_note' => '8pcs: ৳750'],
                    ['name' => 'Crab California Roll', 'price' => 350, 'price_note' => '8pcs: ৳650'],
                    ['name' => 'Ebi Maki Roll', 'price' => 350, 'price_note' => '8pcs: ৳650'],
                    ['name' => 'Karagge Chicken Maki Roll', 'price' => 330, 'price_note' => '8pcs: ৳600'],
                    ['name' => 'Kimbap', 'price' => 790, 'price_note' => '10 pcs'],
                    ['name' => 'Beef Bulgogi Kimbap', 'price' => 890, 'price_note' => '10 pcs'],
                ],
            ],
            [
                'name' => 'Onigiri', 'description' => null,
                'items' => [
                    ['name' => 'Tuna Mayo Onigiri', 'price' => 520],
                    ['name' => 'Furikake Chicken Onigiri', 'price' => 480],
                    ['name' => 'Yaki Onigiri', 'price' => 400],
                ],
            ],
            [
                'name' => 'Bento Box', 'description' => null,
                'items' => [
                    ['name' => 'Nasi Goreng', 'description' => 'Malaysian fried rice, ayam goreng, morning glory fries, satay, fried egg, cucumber salad', 'price' => 550, 'image' => 'food/nasi-goreng.jpg', 'featured' => true],
                    ['name' => 'Seafood Bento', 'description' => 'Seafood fried rice, fried prawn with bang bang stir fried spicy seafood, ebi maki roll', 'price' => 720, 'image' => 'food/seafood-bento.jpg', 'featured' => true],
                    ['name' => 'Beef Bibimbap', 'description' => 'Steamed rice, beef bulgogi, stir fried vegetables, bibimbap sauce and fried egg', 'price' => 590],
                ],
            ],
            [
                'name' => 'Sparkling', 'description' => null,
                'items' => [
                    ['name' => 'Fresh Lime Fizz', 'price' => 210],
                    ['name' => 'Java Lime Tonic', 'price' => 250],
                    ['name' => 'Blueberry Fizz', 'price' => 250],
                    ['name' => 'Virgin Mojito', 'price' => 250],
                    ['name' => 'Pink Poison', 'price' => 190],
                    ['name' => 'Cherry Blossom', 'price' => 290],
                ],
            ],
            [
                'name' => 'Milk Bar', 'description' => null,
                'items' => [
                    ['name' => 'Milo Dinosaur', 'price' => 290],
                    ['name' => 'Milo Godzilla', 'price' => 330],
                    ['name' => 'Neslo', 'price' => 310],
                    ['name' => 'Java Caramel', 'price' => 310],
                ],
            ],
            [
                'name' => 'Tea Time', 'description' => null,
                'items' => [
                    ['name' => 'Ice Citrus Tea', 'price' => 200],
                    ['name' => 'Hokkaido Ice Milk Tea', 'price' => 250],
                    ['name' => 'Milk Tea Float', 'price' => 290],
                    ['name' => 'Long Black Coffee', 'price' => 110],
                    ['name' => 'Flat White Coffee', 'price' => 160],
                ],
            ],
            [
                'name' => 'Soft Drinks & Water', 'description' => null,
                'items' => [
                    ['name' => 'Soda', 'description' => 'Coca-Cola, Sprite, Sprite Mint, or Fanta', 'price' => 50],
                    ['name' => 'Sakura', 'description' => 'Alkaline water', 'price' => 65],
                ],
            ],
        ];

        foreach ($categories as $categoryOrder => $categoryData) {
            $category = RestaurantMenuCategory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => Str::slug($categoryData['name'])],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'] ?? null,
                    'display_order' => $categoryOrder,
                    'is_active' => true,
                ]
            );

            foreach ($categoryData['items'] as $itemOrder => $itemData) {
                RestaurantMenuItem::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'slug' => Str::slug($itemData['name'])],
                    [
                        'restaurant_menu_category_id' => $category->id,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'price' => $itemData['price'],
                        'price_note' => $itemData['price_note'] ?? null,
                        'image_path' => isset($itemData['image']) ? $this->storeSeedImage($itemData['image'], 'restaurant/menu-items') : null,
                        'is_featured' => $itemData['featured'] ?? false,
                        'is_available' => true,
                        'display_order' => $itemOrder,
                    ]
                );
            }
        }
    }

    private function storeSeedImage(string $relativePath, string $targetDir): string
    {
        $sourcePath = database_path(self::SEED_PATH.'/'.$relativePath);
        $targetPath = $targetDir.'/'.basename($relativePath);

        if (! Storage::disk('public')->exists($targetPath)) {
            Storage::disk('public')->put($targetPath, file_get_contents($sourcePath));
        }

        return $targetPath;
    }
}
