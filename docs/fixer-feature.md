This document describes all transformations performed by the `fix` command. It serves as a reference for how Haiku normalizes, sorts, combines, and cleans adblock rules.

Some transformations can be enabled or disabled via the `fixer.flags` configuration option.

```yml
# Example configuration
fixer:
  flags:
    remove_empty_lines: false
    reduce_wildcard_covered_domains: true
```


## Preserved Line

Certain lines are preserved verbatim. The fixer never modifies their content, formatting, or position.

In addition to being preserved, these lines also act as section boundaries. They explicitly separate rule blocks and prevent rules from being sorted, combined, or deduplicated across the boundary.

### # Types

The following lines are preserved unchanged and treated as section separators:

- Comments (`! comment` or `# comment`)
- Preprocessor directives (`!#include /includedfile.txt`, `!#if (conditions)` , etc)

### # Section Boundary Behavior

Rules located on different sides of a preserved line are processed independently.

This means:
- Rules are not combined across the boundary
- Sorting restarts after the boundary
- Each section is optimized in isolation

```adblock
!## BEFORE
a.com##.ads
b.com##.ads
!
c.com##.ads

!## AFTER
a.com,b.com##.ads
!
c.com##.ads
```

In the example above, the comment line (`!`) acts as a hard separator. Although all three rules are compatible, `c.com##.ads` cannot be merged with the rules above because it belongs to a different section.

### # Rationale

This behavior preserves:
- Intentional grouping created by the filter author
- Safety when comments or directives imply semantic separation


## Sorting & Structural Grouping

The fixer does not simply sort rules alphabetically. Instead, it performs structural grouping followed by canonical ordering to produce a predictable and readable.

Rules are always sorted in the following order:

```
[ Network rules ]
[ Cosmetic rules ]
  ├─ Standard
  ├─ CSS & Extended CSS
  └─ Scriptlet
```

```adblock
!## BEFORE
example.com##+js(no-xhr-if, adsbygoogle.js)
||example.com/ads/images/
-banner-ads-$~script
example.com#$#body { background-color: #333!important; }
##.ads2
example.com##.ads1

!## AFTER
-banner-ads-$~script
||example.com/ads/images/
example.com##.ads1
##.ads2
example.com#$#body { background-color: #333!important; }
example.com##+js(no-xhr-if, adsbygoogle.js)
```


## Domain List Sorting

```adblock
!## BEFORE
/ads/*$image,domain=b.com|a.com
b.com,a.com##.ads
[$domain=b.com|a.com]###adblock

!## AFTER
/ads/*$image,domain=a.com|b.com
a.com,b.com##.ads
[$domain=a.com|b.com]###adblock
```

During sorting, negated domains (`~domain`) are placed first, followed by normal domains:

```adblock
!## BEFORE
~d.com,c.com,a.com,~b.com##.ad

!## AFTER
~b.com,~d.com,a.com,c.com##.ad
```


## Rule Combining

Rules that are structurally compatible are merged to reduce redundancy and improve efficiency.

```adblock
!## BEFORE
*$image
*$script
/ads/*$image,domain=example.com
/ads/*$image,domain=example.org
example.com##.ads
example.org##.ads

!## AFTER
*$image,script
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
```

### # Combine Overlapping Options

`fixer.flags.combine_option_sets`

When multiple network filters share the same pattern but differ only in their option sets, the fixer merges them into a single rule.

```adblock
!## BEFORE
-ads-$css,image
-ads-$image
-banner-$css
-banner-$image

!## AFTER
-ads-$css,image
-banner-$css,image
```


## Network Option Ordering

Options are not sorted purely alphabetically. Instead, they are grouped by semantic role and emitted in fixed positions to ensure consistent, readable and visually predictable output.

Ordering rules:
1. `badfilter`, `important`, `match-case`
2. **Party modifiers**: (`strict-first-party`, `1p`, `3p`, ...)
3. **Basic modifiers**: (`script`, `image`, `css`, ...), sorted alphabetically
4. **Key-value modifiers**: (`domain=`, `denyallow=`, `from=`, ...)
5. `reason=`, always placed last

```adblock
!## BEFORE
*$image,script,css,badfilter
-ads-$domain=~example.com,~image,third-party,xmlhttprequest

!## AFTER
*$badfilter,css,image,script
-ads-$third-party,~image,xmlhttprequest,domain=~example.com
```


## Cleanup & Normalization

### # Empty Lines

`fixer.flags.remove_empty_lines`, enabled by default.

Removes empty lines.

```adblock
!## BEFORE
##.ads

##.banner

!## AFTER
##.ads
##.banner
```

### # Duplicate Rules

Removes identical duplicate rules.

```adblock
!## BEFORE
##.banner
##.ads
##.banner

!## AFTER
##.ads
##.banner
```

### # Duplicate Filter Options

Removes duplicate filter options.

```adblock
!## BEFORE
*$image,script,image

!## AFTER
*$image,script
```

### # Duplicate Domains

Removes duplicate domains within a domain list.

```adblock
!## BEFORE
/ads/*$image,domain=example.com|example.com
example.com,example.com##.ads

!## AFTER
/ads/*$image,domain=example.com
example.com##.ads
```

### # Domain Redundancy Elimination

Reduces domain lists by eliminating entries that are semantically covered by more general entries, improving filter list efficiency.

#### Wildcard TLD Coverage

`fixer.flags.reduce_wildcard_covered_domains`

If a wildcard TLD domain (e.g., `example.*`) is present, explicit domains sharing the same base (e.g., `example.com`) are considered redundant.

```adblock
!## BEFORE
example.com,~example.net,example.*##.ads

!## AFTER
~example.net,example.*##.ads
```

Explicit domains covered by a wildcard domain are removed. Negated domains are preserved.

#### Subdomain Coverage

`fixer.flags.reduce_subdomains`

If a base domain is present (e.g., `example.com`), its subdomains (e.g., `api.example.com`) are considered redundant.

```adblock
!## BEFORE
example.com,~ads.example.com,api.example.com,example.org##.ads

!## AFTER
~ads.example.com,example.com,example.org##.ads
```

Subdomains covered by a base domain are removed. Negated domains are preserved.

### # Superfluous Domain Separators

Removes unnecessary or duplicated separators.

```adblock
!## BEFORE
/ads/*$image,domain=|example.com||example.org|
,example.com,,example.org,##.ads

!## AFTER
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
```

### # Space In Domain List

`fixer.flags.normalize_domains`

Remove spaces inside domain lists.

```adblock
!## BEFORE
/ads/*$image,domain= example.com | example.org
example.com , example.org ##.ads

!## AFTER
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
```

### # Wrong Domain Separator

`fixer.flags.normalize_domains`

Corrects incorrect separator usage:
- `|` is used for network rule domain lists
- `,` is used for cosmetic rule domain lists

```adblock
!## BEFORE
-ads-$domain=a.com,b.com,css
example.com|example.org##.ads

!## AFTER
-ads-$css,domain=a.com|b.com
example.com,example.org##.ads
```

### # Domain Symbol

`fixer.flags.normalize_domains`

Removes extraneous symbols accidentally included in domain strings (often caused by copy-paste errors).

```adblock
!## BEFORE
-ads-$domain=example.com/
/example.com##.ads1
.example.org##.ads2

!## AFTER
-ads-$domain=example.com
example.com##.ads1
example.org##.ads2
```

### # Lowercase Domain

Normalizes domain names to lowercase.

```adblock
!## BEFORE
EXAMPLE.COM##.ad

!## AFTER
example.com##.ad
```

### # Lowercase Option Name

Normalizes option names to lowercase.

```adblock
!## BEFORE
*$IMAGE

!## AFTER
*$image
```


## Migrations

### # Filter Option Format

`fixer.flags.option_format`, possible values: `long`, `short`

Normalizes option names to either their long form or short form.

- `long`: Use full-length option names
  (e.g. `$third-party`, `$document`, `$strict-first-party`)

- `short`: Use abbreviated option names
  (e.g. `$3p`, `$doc`, `$strict1p`)

```adblock
! option_format: long

!## BEFORE
*$3p

!## AFTER
*$third-party
```

### # Deprecated Filter Options

`fixer.flags.migrate_deprecated_options`

Migrates deprecated filter options to their modern equivalents.

Supported options: `$empty`, `$mp4`,`$object-subrequest`, `$queryprune`

```adblock
!## BEFORE
*$mp4

!## AFTER
*$media,redirect=noopmp4-1s
