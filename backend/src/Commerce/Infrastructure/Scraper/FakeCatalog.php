<?php

namespace App\Commerce\Infrastructure\Scraper;

use App\Commerce\Domain\CatalogItem;

/**
 * The offline catalog of a store (fake scraper and fake e-commerce platform): the same host always gives the same
 * products, from one of a few kinds of shop, so dev and tests run without the network. Images are left out (a
 * made-up image URL would show as a broken image); the respondent app shows its fallback icon.
 */
final class FakeCatalog
{
    /** @var list<list<array{0: string, 1: string, 2: float}>> */
    private const SHOPS = [
        [
            ['Ethiopia Yirgacheffe beans', 'Bright and floral, with notes of <strong>bergamot</strong> and lemon. Best for pour-over.', 18.5],
            ['Colombia Huila beans', 'Balanced and sweet: caramel, red apple and a <em>silky</em> body. Great for every method.', 16.0],
            ['Sumatra dark roast', 'Earthy and bold, low acidity, notes of cocoa and cedar. Built for espresso and moka pots.', 15.0],
            ['Decaf Swiss Water', 'All the flavour, none of the caffeine: chocolate and hazelnut, chemical-free decaf.', 17.0],
            ['Cold brew blend', 'Coarse-ground and smooth, made to steep overnight. Notes of <strong>dark chocolate</strong>.', 14.0],
            ['Pour-over starter kit', '<p>A ceramic dripper, 100 filters and a scale: everything to start brewing by hand.</p>', 49.0],
            ['Espresso gift box', 'Three espresso roasts in 250 g bags, with a tasting card. A gift for the espresso lover.', 39.0],
            ['Burr grinder', 'Consistent grind from espresso to French press, 40 settings, quiet motor.', 129.0],
            ['Monthly coffee subscription', 'A new single origin every month, roasted the week it ships. Pause anytime.', 22.0],
            ['Travel French press', 'Double-walled steel, keeps coffee hot for 6 hours. Perfect for camping and commuting.', 34.0],
        ],
        [
            ['Gentle foaming cleanser', 'Soap-free cleanser for <strong>sensitive</strong> skin. Removes make-up without drying.', 21.0],
            ['Vitamin C brightening serum', 'Fades dark spots and evens skin tone. 15% vitamin C with ferulic acid.', 38.0],
            ['Hyaluronic hydrating serum', 'Deep hydration for dry and dehydrated skin, light and non-sticky.', 29.0],
            ['Oil-control gel moisturizer', 'Mattifying, oil-free moisturizer for oily and acne-prone skin.', 26.0],
            ['Rich repair cream', 'Ceramides and shea butter for very dry skin. Restores the skin barrier overnight.', 34.0],
            ['Mineral sunscreen SPF 50', 'Broad-spectrum, no white cast, safe for sensitive skin. Daily protection.', 24.0],
            ['Retinol night serum', 'Smooths fine lines and refines texture. Start twice a week.', 42.0],
            ['Exfoliating toner', 'Glycolic and lactic acids for smoother, brighter skin. Use at night.', 23.0],
            ['Soothing eye cream', 'Reduces puffiness and dark circles with caffeine and peptides.', 31.0],
            ['Skincare starter routine', '<p>Cleanser, serum and moisturizer in travel sizes: find the routine that fits you.</p>', 55.0],
        ],
        [
            ['Road runner daily trainer', 'Cushioned, durable trainer for <strong>everyday road runs</strong> and beginners.', 120.0],
            ['Trail grip pro', 'Aggressive lugs and a rock plate for technical mountain trails.', 145.0],
            ['Race day carbon flyer', 'Carbon plate and light foam for race-day speed on the road.', 230.0],
            ['Stability support shoe', 'Extra support for overpronation and long, steady miles.', 135.0],
            ['Minimalist barefoot runner', 'Zero drop, flexible sole for a natural stride. For experienced runners.', 110.0],
            ['Waterproof winter runner', 'Waterproof upper and warm lining for rain, cold and snow.', 150.0],
            ['Recovery slide', 'Soft foam slide to recover after long runs.', 45.0],
            ['Running socks 3-pack', 'Moisture-wicking, blister-free cushioned socks.', 24.0],
            ['Hydration vest', 'Light vest with two soft flasks for long runs and trails.', 85.0],
            ['Reflective running jacket', 'Wind- and water-resistant, highly visible at night.', 98.0],
        ],
    ];

    /** @return list<CatalogItem> */
    public static function of(string $origin, int $limit): array
    {
        $host = strtolower((string) (parse_url($origin, \PHP_URL_HOST) ?: $origin));
        $base = 'https://'.$host;
        $items = [];
        foreach (\array_slice(self::SHOPS[crc32($host) % \count(self::SHOPS)], 0, max(0, $limit)) as [$name, $description, $price]) {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
            $item = CatalogItem::from($name, $description, $price, null, $base.'/products/'.$slug);
            if (null !== $item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
