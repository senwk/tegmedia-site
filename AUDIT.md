# tegmedia.my content audit

29 September 2026. Read from the live site through WordPress's public API: every page, the
news posts, the shop products and the media library. Nothing on the live site was changed.

## Fix now, whatever happens with the redesign

1. **A shop product is priced at RM0.01.** "Magazine Subscription" (product 5832) is live at one
   sen. Anyone can buy it. Unpublish it or correct the price.
2. **A test page is public:** `/subscribe-magazine-test-page/`.
3. **Two empty leftovers are public:** `/shop-2/` (from 2023) and `/magazine-subscription/` (two words).
4. **Two prices for the same subscription.** The Expat's print subscription is RM150 a year on
   `/magazine-subscription-the-expat/` and in the shop, but RM10 a copy, RM120 a year, on
   `/magazine-subsription-expat-education-guide/`. That second page is also misnamed: its slug says
   Education Guide and its content is The Expat.
5. **"Recent News" ends in September 2020.** The newest post is a Covid update. A visitor reads
   that as a company that stopped in 2020.
6. **`/mm2h-membership-subscription/` shows the Education Guide page**, not an MM2H subscription.

## Figures on the site that do not match what has been confirmed

| Where | The site says | What we use now |
|---|---|---|
| Homepage | "We publish ten magazines" | The Expat, the Welcome Guide, the Education Guide (Senses of Malaysia to confirm) |
| Homepage, ExpatGo page | 350,000+ monthly online readers | ~40,000 ExpatGo users a month (Mike, 18 Sep 2026) |
| ExpatGo page | "Top 1,000 websites... as indicated by Alexa.com" | Alexa closed in 2022, so the source no longer exists. Remove |
| ExpatGo page | 50,000+ followers and subscribers | 23,910 opted-in newsletter readers; social figure to confirm |
| Welcome Guide page | 10,000 copies, 140+ pages, January | 8,000 copies, ~100 pages (2027 rate card) |
| Education Guide page | 10,000 copies, A4, 56 pages | 11,500 copies for 2026 |
| Senses of Malaysia page | Bi-monthly, 10,000 copies, on Malaysia Airlines | Described as live; the title is being revived. Confirm before it stays |
| MM2H page | mm2h.com has 8,000+ monthly readers | Unverified |
| MM2H Visa Agency page | "We refund most of the fee should the application be unsuccessful" | Check against current terms before it stays |
| WhiskyPLUS page | Stops at 2019 ("planned a Penang edition") | Needs the editions since |

Two absolute claims worth softening, in the way Andy softened the ITP overview: The Expat as "the
most widely read publication by resident expats", and WhiskyPLUS as "Malaysia's largest whisky
and spirits tasting event".

## Every page, and what to do with it

**Rebuild in the new design**

| Page | Last changed | Note |
|---|---|---|
| Home (`/home-one/`) | 14 Aug | The figures above need correcting |
| About | 26 Jul | Keep the four-companies paragraph and the team of ten with photos. Best content on the site |
| Contact | 18 Aug | Settle the public set: info@ or helpdesk@, and whether the fax stays |
| The Expat (`/the-expat/`) | 8 Jul | Thin (107 words). Merge with the two below |
| The Expat Welcome Guide | 8 Jul | Thin (97 words); figures out of date |
| Expat Education Guide | 18 Jul | Thin (81 words); figures out of date |
| ExpatGo | 4 Jul | Thin (66 words); figures out of date |
| MM2H Visa Agency | 4 Jul | Merge with MM2H below into one page pointing to mm2h.com |
| The Mingle, The Wine Dinner, WhiskyPlus, TEG Card | 4 to 8 Jul | Combine into one Events page, with TEG Card on it |
| Read Online | 31 Aug | Keep. The back catalogue of covers is strong material |

**Merge or remove**

| Page | Why |
|---|---|
| The Expat Magazine (`/the-expat-magazine/`) | Duplicate of The Expat, older (May). Same text as Read Online |
| MM2H (`/mm2h/`) | Duplicate of MM2H Visa Agency |
| Senses of Malaysia | Keep only if the title is live |
| Recent News (`/blog/`) | Restart it with a commitment to keep it current, or take it out of the menu |
| Magazine Subscription, both versions, and MM2H Membership Subscription | Replace with one Subscribe page that lists every product |
| Subscribe Magazine Test Page, Shop (`/shop-2/`) | Test and leftover pages. Unpublish |

**Keep, restyle only:** Shop, Cart, Checkout, My account, Shipping Policy, Return & Refund Policy.
These are WooCommerce's working pages; they take payments and must keep working through the switch.

## Worth keeping from the archive

The 18 news posts, 2007 to 2020, hold real history: Senses of Malaysia's Malaysia Tourism
Awards in 2010 and 2014, the 2020 renewal to publish The Circular for The Royal Selangor Golf
Club, NTV7 and InvestKL appearances. They belong on an "Our story" timeline rather than in a news
feed that stopped in 2020. The posts also settle that the Tourism Awards belong to Senses of
Malaysia, not The Expat.

The media library holds around 30 Expat covers since 2023, the current covers of all three
guides, double-page spreads, 15 client logos and staff portraits. Two "photos"
(`tegmedia-corporate-site-graphic.jpg`, `tegmedia-graphic-banner-mm2h.jpg`) look AI-generated
and should not go on a premium site.

## Proposed structure: 29 pages to about 16

Home · About (story, team, the group) · Our brands (The Expat, Welcome Guide, Education Guide,
ExpatGo) · Events (The Mingle, Wine Dinners, WhiskyPLUS, TEG Card) · MM2H (one page, to
mm2h.com) · Advertise (new: packages, rate card, enquiry) · Read online · Subscribe (one page) ·
News (only if kept current) · Contact · plus Shop, Cart, Checkout, My account and the two policies.

**Advertise is the page missing today.** The site sells to readers (subscriptions, the TEG Card)
but has no page for the advertisers who pay for the business.

## Under the bonnet

WordPress 7.0.4 on TEG Digital's cPanel server (venom.teg-digital.com, hosted at Shinjiru).
Theme **Metrobank**, a banking template, with Elementor and five add-on packs, WooCommerce,
GetGenie AI and Jetpack. The homepage weighs **5.9MB across 250 requests** (118 scripts, 133
stylesheets).
