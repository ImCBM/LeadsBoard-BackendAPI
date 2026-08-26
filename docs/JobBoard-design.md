---
name: JobBoard
colors:
  surface: '#faf9f2'
  surface-dim: '#e2decf'
  surface-bright: '#faf9f2'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f4f1e6'
  surface-container: '#eeeadb'
  surface-container-high: '#e7e2d0'
  surface-container-highest: '#dfd9c4'
  on-surface: '#1e2a22'
  on-surface-variant: '#52584a'
  inverse-surface: '#303b32'
  inverse-on-surface: '#f1efe2'
  outline: '#7e8474'
  outline-variant: '#cac5b0'
  surface-tint: '#1fa97d'
  primary: '#1fa97d'
  on-primary: '#ffffff'
  primary-container: '#c9f1e1'
  on-primary-container: '#08402f'
  inverse-primary: '#86e0be'
  secondary: '#e8724a'
  on-secondary: '#ffffff'
  secondary-container: '#fbdccc'
  on-secondary-container: '#7a2e10'
  tertiary: '#3e93b8'
  on-tertiary: '#ffffff'
  tertiary-container: '#cfebf5'
  on-tertiary-container: '#0f3c4c'
  error: '#c13f2c'
  on-error: '#ffffff'
  error-container: '#fad9d0'
  on-error-container: '#6b1b0c'
  primary-fixed: '#b2ecd5'
  primary-fixed-dim: '#86e0be'
  on-primary-fixed: '#03301f'
  on-primary-fixed-variant: '#106647'
  secondary-fixed: '#ffdbc7'
  secondary-fixed-dim: '#f5b594'
  on-secondary-fixed: '#451b02'
  on-secondary-fixed-variant: '#8a3f1c'
  tertiary-fixed: '#c0e6f2'
  tertiary-fixed-dim: '#8fcde1'
  on-tertiary-fixed: '#06293a'
  on-tertiary-fixed-variant: '#235972'
  background: '#faf9f2'
  on-background: '#1e2a22'
  surface-variant: '#e4dfcc'
typography:
  display-lg:
    fontFamily: Poppins
    fontSize: 64px
    fontWeight: '600'
    lineHeight: 72px
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Poppins
    fontSize: 40px
    fontWeight: '600'
    lineHeight: 48px
    letterSpacing: -0.005em
  headline-lg-mobile:
    fontFamily: Poppins
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
    letterSpacing: 0em
  headline-md:
    fontFamily: Poppins
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: 0em
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 26px
    letterSpacing: 0em
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 22px
    letterSpacing: 0em
  label-caps:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.08em
  button:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0em
spacing:
  unit: 8px
  container-max: 1280px
  gutter: 24px
  margin-desktop: 64px
  margin-mobile: 20px
  section-gap: 96px
---

## Brand & Style
The design system embodies "Grown, Not Manufactured"—a philosophy where the platform feels cultivated with care rather than assembled from a generic template. It serves two audiences at once, employers and jobseekers, who need to trust the same interface with very different stakes: headcount on one side, income on the other.

The visual style is **Fresh Minimalism**. It takes its cues from nature—new growth, open air, morning light—without ever collapsing into "everything is green." Key principles include:
- **A Dominant Hue, Not a Monochrome:** Mint carries the brand, but it is set within a proper color scheme rather than repeated at different opacities.
- **Warmth over Sterility:** Rounded geometry and a warm neutral base keep the product from reading as cold, generic SaaS.
- **Clarity First:** Friendly never means vague—hierarchy and contrast must make the next action obvious to both a hiring manager and a first-time jobseeker alike.

## Colors
The palette is built on **color theory, not vibes**: mint sits at roughly 155° on the wheel, so its secondary is pulled from a split-complementary position (~15°, warm coral) rather than a true complement, giving contrast and energy without visual clash. The result follows a 60/30/10 balance—60% warm neutral, 30% mint, 10% coral—so mint reads as dominant without becoming monotonous.

- **Primary (Mint):** The brand's main voice—primary actions, active nav, links. Carries roughly 30% of any given screen.
- **Secondary (Coral):** A split-complementary accent used sparingly (~10%) for the single most important action or highlight on a screen—this is what keeps the palette from reading as "all green."
- **Accent (Sky):** An analogous cool hue near mint on the wheel, used for informational states, messaging, and secondary data series—it stays inside the same "fresh air" family instead of introducing a fourth unrelated hue.
- **Backgrounds (Warm Ivory):** Never pure white—an ivory base with a faint warm-green undertone keeps long dashboard sessions comfortable and ties neutrals back to the primary hue.
- **Surfaces (Sage/Stone):** Low-contrast, slightly cooler-neutral containers used for cards and dividers to separate content without adding visual noise.

**Rule—no ad-hoc badge colors:** Every colored element in the product, including table badges, tier labels, and system-status pills, must be built from this five-color system (Mint / Coral / Sky / neutral Surface-Containers / Error) and nothing else. It is easy to reach for an unthemed neon purple or lime-green pill because a category "needs its own color"—that is exactly what breaks the system. If a badge doesn't map cleanly to Mint, Coral, Sky, Error, or a neutral Surface-Container, use a neutral one rather than inventing a new hue.

## Typography
The typographic hierarchy relies on the pairing of a rounded, friendly **Poppins** for display type and a highly legible, neutral **Inter** for everything functional.

- **Headlines:** Use Poppins Semibold for marketing headlines, page titles, and card/section titles alike—one weight, used consistently, keeps dense dashboard screens feeling connected to the marketing site rather than like a different product.
- **Body:** Use Inter for all functional copy, forms, and tables. Its neutrality keeps mint, coral, and sky doing the work of expressing brand—not the typeface.
- **Labels:** Use "Label-Caps" for filter chips, status tags, and eyebrows. Tracking is moderate (0.08em)—enough to feel intentional without tipping into stiff or formal.
- **Alignment:** Left-align by default, including most hero sections—this is a working tool people return to, not an editorial spread. Reserve centered text for narrow empty-states and confirmations.

## Layout & Spacing
The layout philosophy is a **12-column grid** tuned for two different modes: a calmer marketing surface and a denser working dashboard.

- **Grid:** 1280px max container, with 64px desktop margins on marketing pages; in-app dashboard views may pull margins in to fit more working content.
- **Vertical Rhythm:** 96px gap between major marketing sections—enough room to feel calm without the slow, editorial pacing of a luxury brand. Dashboard modules use a tighter, consistent 24px gutter.
- **Responsive Behavior:** On mobile, margins reduce to 20px and section gaps compress to 48px. Sidebar navigation collapses to icon-only on tablet and to a bottom bar or drawer on mobile; multi-column stat cards stack to a single column.

## Elevation & Depth
Depth is achieved through **soft tonal layers plus gentle, warm-tinted shadows**—enough lift to feel tactile and friendly, never harsh.

- **Surfaces:** Use shifts in warm neutral value (e.g., a Sage container on an Ivory background) to indicate hierarchy before reaching for shadow.
- **Borders:** When needed, thin (1px), low-contrast lines (Outline-Variant) frame inputs and low-emphasis cards alongside soft shadow, not instead of it.
- **Shadows:** Cards and popovers use diffused, warm-tinted ambient shadows; modals use a stronger shadow plus a scrim, so focused tasks are unmistakably separated from the page behind them.

## Shapes
The shape language is **Soft & Rounded (8–24px)**.

To reflect the ease and approachability of nature rather than architecture, all UI elements—buttons, inputs, cards, and images—use a consistent rounded scale: 8px for inputs and small controls, 12–16px for cards and modals, up to 24px for large hero media, and fully rounded (pill) for chips, tags, and toggles. Never mix a sharp corner into this system—consistency in roundness is what reads as considered rather than accidental.

## Components
- **Buttons:** Primary buttons are solid Mint with white text. Secondary/emphasis buttons use Coral, reserved for the one most important action on a screen. All buttons use the "Button" typography role, pill or 8px radius, no letter-spacing.
- **Inputs:** Full-width boxes with a 1px Outline-Variant border, 8px radius, and a soft Surface-Container-Low fill—never stark white. Labels sit above in plain-case Body-Md; focus state uses a Mint-tinted border and subtle glow.
- **Cards:** No hard shadows—use a Sage/Surface-Container fill plus a soft ambient shadow, with generous internal padding (min 24px, 32px for stat cards). Status icons rotate through Mint, Coral, and Sky containers so metrics don't blur into one color.
- **Chips/Filters:** Use "Label-Caps" typography, pill radius. Active states are solid Mint with white text; inactive states are Surface-Container fill with On-Surface-Variant text.
- **Badges & Tags (tables/lists):** No unthemed neon or pastel one-offs (no stray purple, lime, magenta). Map every badge to the system: positive/new/success states use Primary-Container mint with On-Primary-Container text; role or tier labels (e.g. "C-Level") use a neutral Surface-Container-High with On-Surface-Variant text, not a random accent color; system/technical states (e.g. an "OK" status) use Tertiary-Container sky, not neon green; plain metadata counts (e.g. employee count) use Surface-Container fill with On-Surface-Variant text—reserve Mint and Coral for things the user should actually notice.
- **Images:** Real, warm photography of people at work, never cold stock offices. Use a soft dark scrim under overlaid text and a consistent large radius or full-bleed edge.
- **Navigation:** A persistent left sidebar (collapsible to icon-only) with the active item shown as a solid Mint pill, collapsing to a bottom tab bar or drawer on mobile so wayfinding stays obvious for infrequent jobseeker-side visits.