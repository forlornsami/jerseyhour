<?php
/**
 * Default settings, pages and optional demo catalogue used by the installer.
 */

function seed_settings(PDO $pdo, array $overrides = []): void
{
    $defaults = [
        'store_name'          => 'JerseyHour',
        'tagline'             => 'Wear your passion. Premium football & cricket jerseys, delivered across Pakistan.',
        'phone'               => '+92 342 0052034',
        'whatsapp'            => '+923420052034',
        'email'               => 'gullrahat27@gmail.com',
        'address'             => 'SF429, Deans Trade Center, Saddar, Peshawar',
        'hours'               => 'Mon – Sat, 11:00 AM – 9:00 PM',
        'currency_symbol'     => 'Rs.',
        'delivery_fee'        => '250',
        'free_shipping_over'  => '5000',
        'delivery_time'       => '2 – 5 working days',
        'size_list'           => 'XS,S,M,L,XL,XXL',
        'announcement'        => 'Free delivery on orders over Rs. 5,000 · Cash on Delivery nationwide',
        'hero_eyebrow'        => 'New Season · 25/26 Drop',
        'hero_title'          => 'Wear your passion.',
        'hero_subtitle'       => 'Fan & player-edition jerseys for football and cricket. Premium fabric, true-to-size fits, and Cash on Delivery across Pakistan.',
        'hero_image'          => '',
        'instagram'           => '',
        'facebook'            => '',
        'tiktok'              => '',
        'meta_description'    => 'JerseyHour — premium football and cricket jerseys in Pakistan. Club, national team and retro kits with Cash on Delivery nationwide. Based in Saddar, Peshawar.',
        'order_notify_email'  => 'gullrahat27@gmail.com',
        'logo_image'          => '',
        'logo_light'          => '',
        'logo_height'         => '50',
        'favicon'             => '',
        'brand_color'         => '#7A2230',
    ];
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach (array_merge($defaults, $overrides) as $k => $v) {
        $st->execute([$k, $v]);
    }
}

function seed_pages(PDO $pdo): void
{
    $now = date('Y-m-d H:i:s');
    $pages = [
        ['about', 'About JerseyHour', "JerseyHour was started in Peshawar by fans, for fans. We believe a jersey is more than a shirt — it's matchday, it's your team, it's the moment the whole street goes quiet before a penalty.\n\nWe stock carefully selected football and cricket jerseys: current-season club kits, national team shirts and retro classics. Every piece is checked for stitching, print quality and sizing before it leaves our store.\n\n## Why shop with us\n- Quality-checked jerseys with accurate size charts\n- Cash on Delivery anywhere in Pakistan\n- Easy size exchange within 7 days\n- Real people on WhatsApp to help you choose\n\nVisit us at our store in Deans Trade Center, Saddar, Peshawar — or order online and we'll bring the jersey to your door."],
        ['shipping-policy', 'Shipping Policy', "We deliver to every city in Pakistan through trusted courier partners.\n\n## Delivery time\n- Peshawar: 1 – 2 working days\n- Other major cities: 2 – 4 working days\n- Remote areas: 4 – 7 working days\n\n## Delivery charges\nA flat delivery charge applies to each order. Orders above the free-delivery threshold shown at checkout ship free.\n\n## Order confirmation\nAfter you place an order, our team will call or WhatsApp you to confirm it before dispatch. Please keep your phone reachable — unconfirmed orders may be cancelled after 48 hours."],
        ['returns-exchange', 'Returns & Exchange', "We want your jersey to fit perfectly.\n\n## Size exchange\nIf the size isn't right, you can request an exchange within **7 days** of delivery. The jersey must be unworn, unwashed and with original tags.\n\n## Damaged or wrong item\nIf you receive a damaged or incorrect item, message us on WhatsApp within 48 hours with photos and we'll replace it at no extra cost.\n\n## Not eligible\n- Customised jerseys (name / number printing)\n- Items that have been worn, washed or altered\n\nTo start an exchange, contact us on WhatsApp with your order number."],
        ['privacy-policy', 'Privacy Policy', "Your privacy matters to us.\n\n## What we collect\nWhen you place an order we collect your name, phone number, email (optional), city and delivery address. We use this only to process and deliver your order and to contact you about it.\n\n## What we don't do\nWe never sell or share your personal information with third parties, except the courier company that delivers your order.\n\n## Contact\nFor any privacy question, email us or send a WhatsApp message."],
        ['size-guide', 'Size Guide', "Measure a jersey you already own: lay it flat and measure straight across the chest from armpit to armpit, then from the top of the shoulder to the bottom hem.\n\n| Size | Chest (inches) | Length (inches) |\n| S | 19 | 27 |\n| M | 20 | 28 |\n| L | 21 | 29 |\n| XL | 22 | 30 |\n| XXL | 23 | 31 |\n\n**Tip:** Player-edition jerseys have a slim, athletic fit — if you're between sizes, go one size up."],
    ];
    $st = $pdo->prepare('INSERT INTO pages (slug, title, content, updated_at) VALUES (?, ?, ?, ?)');
    foreach ($pages as $p) {
        $st->execute([$p[0], $p[1], $p[2], $now]);
    }
}

function seed_categories(PDO $pdo): array
{
    $now = date('Y-m-d H:i:s');
    $cats = [
        ['Club Jerseys', 'club-jerseys', 'Home, away and third kits from the season.', 'assets/img/demo/jersey-1.svg'],
        ['National Teams', 'national-teams', 'Represent your country on matchday.', 'assets/img/demo/jersey-7.svg'],
        ['Cricket Kits', 'cricket-kits', 'Fan and player-edition cricket shirts.', 'assets/img/demo/jersey-3.svg'],
        ['Retro Classics', 'retro-classics', 'Iconic designs from the golden era.', 'assets/img/demo/jersey-4.svg'],
    ];
    $ids = [];
    $st = $pdo->prepare('INSERT INTO categories (name, slug, description, image, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($cats as $i => $c) {
        $st->execute([$c[0], $c[1], $c[2], $c[3], $i, $now]);
        $ids[$c[1]] = (int)$pdo->lastInsertId();
    }
    return $ids;
}

function seed_products(PDO $pdo, array $cat): void
{
    $now = time();
    $sz = fn(array $a) => json_encode(array_combine(['S', 'M', 'L', 'XL', 'XXL'], $a));
    $list = [
        ['Royal Stripe Home Jersey 25/26', 'club-jerseys', 4500, 5500, 'New', 1, [6, 10, 12, 8, 4], 1,
         "Our best-selling home kit for the new season. Breathable, quick-dry polyester with a soft-touch finish and embroidered crest.\n\n- Fan edition, regular fit\n- 100% recycled polyester, moisture-wicking\n- Embroidered crest and heat-pressed trims\n- Machine wash cold, inside out"],
        ['Crimson Hoops Away Jersey', 'club-jerseys', 4200, null, null, 1, [4, 8, 9, 6, 2], 2,
         "Bold crimson hoops made for away days. Lightweight mesh panels keep you cool from kick-off to full time.\n\n- Fan edition, regular fit\n- Lightweight breathable fabric\n- Ribbed V-neck collar"],
        ['Green Shirts Cricket Fan Jersey', 'cricket-kits', 3800, 4500, 'Hot', 1, [10, 15, 15, 10, 6], 3,
         "Cheer from the stands in this classic green cricket fan jersey with a sublimated star pattern.\n\n- Sublimated print that won't crack or fade\n- Soft stretch fabric for all-day comfort\n- Regular fit"],
        ['Retro 98 Sash Classic', 'retro-classics', 5200, null, 'Retro', 1, [3, 5, 5, 3, 1], 4,
         "A tribute to the golden era — a crisp white shirt with the iconic diagonal sash.\n\n- Heavyweight retro fabric\n- Button-down collar\n- Embroidered details"],
        ['Midnight Third Kit', 'club-jerseys', 4800, null, 'Player Edition', 0, [2, 6, 6, 4, 0], 5,
         "The stealth third kit in deep midnight with subtle tonal detailing. Player edition — slim, athletic fit.\n\n- Player edition, slim fit (size up if between sizes)\n- Laser-cut ventilation\n- Heat-bonded crest"],
        ['Sky Blue Heritage Jersey', 'retro-classics', 4400, 5000, null, 0, [4, 6, 7, 5, 2], 6,
         "Soft sky-blue heritage shirt with contrast navy collar and cuffs. Timeless on and off the pitch.\n\n- Regular fit\n- Cotton-feel polyester\n- Contrast collar"],
        ['Sunburst National Jersey', 'national-teams', 4000, null, 'New', 1, [8, 10, 10, 8, 4], 7,
         "Bright sunburst yellow with green trims — made for matchday in the stands or the park.\n\n- Fan edition, regular fit\n- Moisture-wicking fabric\n- Printed crest"],
        ['Tri-Colour Training Top', 'club-jerseys', 3200, 3800, 'Sale', 0, [6, 10, 10, 6, 3], 8,
         "A light training top with a split tri-colour design. Perfect for five-a-side, the gym or everyday wear.\n\n- Lightweight training fabric\n- Crew neck\n- Quick-dry"],
    ];
    $st = $pdo->prepare('INSERT INTO products (category_id, name, slug, description, price, compare_price, sizes, images, badge, featured, active, views, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)');
    foreach ($list as $i => $p) {
        $ts = date('Y-m-d H:i:s', $now - ($i * 3600 * 20));
        $imgs = ['assets/img/demo/jersey-' . $p[7] . '.svg', 'assets/img/demo/jersey-' . $p[7] . '-back.svg'];
        $st->execute([$cat[$p[1]] ?? null, $p[0], slugify($p[0]), $p[8], $p[2], $p[3], $sz($p[6]), json_encode($imgs), $p[4], $p[5], $ts, $ts]);
    }
}
