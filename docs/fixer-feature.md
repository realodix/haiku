This document describes all transformations performed by the `fix` command. It serves as a reference for how Haiku normalizes, sorts, combines, and cleans adblock rules.


## Preserved Line

The fixer preserves certain lines verbatim and never modifies their content, formatting, or position.

In addition to being preserved, these lines also act as section boundaries. They explicitly separate rule blocks and prevent rules from being sorted, combined, or deduplicated across the boundary.

### Types

The following lines are preserved unchanged and treated as section separators:

- Comments (`! comment` or `# comment`)
- Preprocessor directives (`!#include /includedfile.txt`, `!#if (conditions)` , etc)

### Section Boundary Behavior

Rules located on different sides of a preserved line are processed independently.

This means:
- Rules are not combined across the boundary
- Sorting restarts after the boundary
- Each section is optimized in isolation

```adblock
! before
a.com##.ads
b.com##.ads
!
c.com##.ads

! after
a.com,b.com##.ads
!
c.com##.ads
```

In the example above, the comment line (`!`) acts as a hard separator. Although all three rules are compatible, `c.com##.ads` cannot be merged with the rules above because it belongs to a different section.

### Rationale

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
! before
example.com##+js(no-xhr-if, adsbygoogle.js)
||example.com/ads/images/
-banner-ads-$~script
example.com#$#body { background-color: #333!important; }
##.ads2
example.com##.ads1
example.org#?#div:has(> a[target="_blank"][rel="nofollow"])

! after
-banner-ads-$~script
||example.com/ads/images/
example.com##.ads1
##.ads2
example.com#$#body { background-color: #333!important; }
example.org#?#div:has(> a[target="_blank"][rel="nofollow"])
example.com##+js(no-xhr-if, adsbygoogle.js)
```


## Domain List Sorting

```adblock
! before
/ads/*$image,domain=b.com|a.com
b.com,a.com##.ads
[$domain=b.com|a.com]###adblock

! after
/ads/*$image,domain=a.com|b.com
a.com,b.com##.ads
[$domain=a.com|b.com]###adblock
```

Negated domains will always be put before normal domains:

```adblock
! before
~d.com,c.com,a.com,~b.com##.ad

! after
~b.com,~d.com,a.com,c.com##.ad
```


## Rule Combining

```adblock
! before
*$image
*$script
/ads/*$image,domain=example.com
/ads/*$image,domain=example.org
example.com##.ads
example.org##.ads

! after
*$image,script
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
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
! before
*$image,script,css,badfilter
/ads/*$domain=~example.com,~image,third-party,xmlhttprequest

! after
*$badfilter,css,image,script
/ads/*$third-party,~image,xmlhttprequest,domain=~example.com
```


## Normalization & Cleanup

### Duplicates
```adblock
! before
/ads/*$css,image
/ads/*$image
##.ads
##.ads

! after
/ads/*$css,image
##.ads
```

```adblock
! before
*$image,image
/ads/*$image,domain=example.com|example.com
example.com,example.com##.ads

! after
*$image
/ads/*$image,domain=example.com
example.com##.ads
```

### Lowercase
```adblock
! before
*$IMAGE
EXAMPLE.COM##.ad

! after
*$image
example.com##.ad
```

### Superfluous Separators
```adblock
! before
/ads/*$image,domain=|example.com||example.org|
,example.com,,example.org,##.ads

! after
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
```

### Wrong Separator
```adblock
! before
example.com|example.org##.ads

! after
example.com,example.org##.ads
```

### Domain Symbol
```adblock
! before
example.com/##.ads1
.example.org##.ads2

! after
example.com##.ads1
example.org##.ads2
```
