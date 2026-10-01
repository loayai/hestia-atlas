# Layout reference

Hestia Atlas's page structure is inspired by the general organisation of the cPanel®
Jupiter interface, so that people used to that control panel find their way quickly.
This page records the *structural* patterns that informed the layout, studied from
cPanel's public documentation (docs.cpanel.net: *The cPanel Interface*, *Email Accounts*,
*Domains*). It deliberately contains no colours, measurements, assets or code from cPanel;
the visual system is original and documented in [design-system.md](design-system.md).

| Pattern | Observed in the reference | Hestia Atlas |
| --- | --- | --- |
| Navigation | Persistent left column with icon + label entries | Left column with grouped, expandable sections mapped to Hestia features; icon rail on narrow desktops; off-canvas drawer on tablets and phones |
| Header | Search on the right, notifications, account menu | "Find a tool or page" search (also searches Hestia objects), Hestia notifications, account menu |
| Home page | Grid of tools grouped into collapsible sections, plus an account/usage column | **Tools** page built from Hestia's features; *Account Overview*, *Server Status* (administrators) and *Resource Usage* from real Hestia data |
| Usage information | Label / value rows and progress bars | Rows with meters that change colour near limits |
| List pages | Title, short description, toolbar (search, filters, bulk actions, create), table with row actions | Breadcrumb + title + description above Hestia's toolbar and table; labelled primary row action; destructive actions marked |
| Forms | Single column, actions at the end | Hestia's forms in a card, Save moved (not duplicated) to an action bar with Cancel |
| Responsive | Navigation collapses, side column stacks, tables reflow | Rail → drawer, side column below tools, Hestia's tables become labelled cards |

The section model (Email, Files, Databases, Domains, Metrics, Security, Advanced,
Preferences) uses plain descriptions of hosting functions; Hestia's administrator areas
(Administration, Server) are placed first for administrators, as Hestia itself does.
