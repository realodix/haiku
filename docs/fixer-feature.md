This document describes all transformations performed by the `fix` command. It serves as a reference for how Haiku normalizes, sorts, combines, and cleans adblock rules.

Some of these transformations may be enabled or disabled using the `fixer.flags` configuration option.

```yml
# Example configuration
fixer:
  flags:
    remove_empty_lines: false
    reduce_wildcard_covered_domains: true
```


## Preserved Line

The fixer preserves certain lines verbatim and never modifies their content, formatting, or position.

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

During sorting, negated domains (`~domain`) come first, followed by normal domains:

```adblock
!## BEFORE
~d.com,c.com,a.com,~b.com##.ad

!## AFTER
~b.com,~d.com,a.com,c.com##.ad
```


## Rule Combining

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

`fixer.flags.xmode`

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

Options are not sorted purely alphabetically. Instead, they are grouped by semantic role and emitted in fixed positions:

1. **Rule modifiers**: (`badfilter`, `important`, `match-case`)
2. **Party / context modifiers**: (`strict-first-party`, `1p`, `3p`, etc.)
3. **Basic request modifiers**: (`script`, `image`, `css`, ...), sorted alphabetically
4. **Key-value modifiers**: (`domain=`, `denyallow=`, `from=`, ...)
5. **Metadata modifiers**: (`reason=`), always placed last

This canonicalization ensures visually predictable rules.

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

Removes duplicate rules.

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

Removes duplicate domains.

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

When a wildcard TLD domain (`example.*`) is present, all explicit domains with the same base are considered redundant.

```adblock
!## BEFORE
example.com,~example.net,example.*##.ads

!## AFTER
~example.net,example.*##.ads
```

Explicit domains covered by a wildcard domain are removed. Negated domains are preserved.

#### Subdomain Coverage

`fixer.flags.reduce_subdomains`

When a base domain is present, all its subdomains are considered redundant.

```adblock
!## BEFORE
example.com,~ads.example.com,api.example.com,example.org##.ads

!## AFTER
~ads.example.com,example.com,example.org##.ads
```

Subdomains covered by a base domain are removed. Negated domains are preserved.

### # Superfluous Domain Separators

Removes unneeded separators.

```adblock
!## BEFORE
/ads/*$image,domain=|example.com||example.org|
,example.com,,example.org,##.ads

!## AFTER
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
```

### # Space In Domain List

Remove spaces within the domain list.

```adblock
!## BEFORE
/ads/*$image,domain= example.com | example.org
example.com , example.org ##.ads

!## AFTER
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
```

### # Wrong Domain Separator

`fixer.flags.xmode`

Corrects separator syntax errors where the wrong separator is used for the context (`,` in network rules, `|` in cosmetic rules).

```adblock
!## BEFORE
-ads-$domain=a.com,b.com,css
example.com|example.org##.ads

!## AFTER
-ads-$css,domain=a.com|b.com
example.com,example.org##.ads
```

### # Domain Symbol

`fixer.flags.xmode`

Removes extraneous symbols from domain strings that may result from copy-paste errors.

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
```adblock
!## BEFORE
EXAMPLE.COM##.ad

!## AFTER
example.com##.ad
```

### # Lowercase Option Name
```adblock
!## BEFORE
*$IMAGE

!## AFTER
*$image
```


## Migrations

### # Deprecated Filter Options

`fixer.flags.xmode`

Migrates deprecated filter options to their modern equivalents.

Supported options:
- `$empty`
- `$mp4`
- `$queryprune`

```adblock
!## BEFORE
*$mp4

!## AFTER
*$media,redirect=noopmp4-1s
