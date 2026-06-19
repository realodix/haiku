# Linter Rules Documentation

This document outlines all the rules used by the Haiku Linter to analyze and enforce best practices in filter lists (e.g., uBlock Origin, AdGuard, EasyList).

The rules are grouped by category based on the type of filter they check (e.g., Cosmetic, Network, Preprocessor).

---

## Cosmetic Rules

These rules validate the syntax and structure of cosmetic filters, which are used to hide or modify elements on a webpage.

### id-selector-starts-with-digit

**Description**

Checks for ID selectors that start with a digit. In CSS, an ID selector like `#123foo` is invalid. While it might be interpreted, it is a common source of errors and should be escaped or renamed.

**Why this matters**

Using an ID that starts with a number can lead to inconsistent behavior across different browsers and filtering engines. Escaping the first digit using its Unicode code point (e.g., `\31 23foo`) is the proper way to handle this.

**Invalid**

```txt
example.com###123foo
###42bar
```

**Valid**

```txt
example.com###foo123
##\31 23foo
```

---

### abp-extended-css-selector

**Description**

Enforces the correct separator syntax for Adblock Plus (ABP) extended CSS selectors (`:-abp-has`, `:-abp-contains`, `:-abp-properties`). These selectors must be used with the `#?#` separator, not the standard `##`.

**Why this matters**

Using the `#?#` separator is required for the browser's CSS engine to properly interpret these pseudo-classes. Using `##` will result in a syntax error and the filter will not work.

**Invalid**

```txt
example.com##:-abp-has(.sponsored)
example.com##:-abp-contains(ads)
```

**Valid**

```txt
example.com#?#:-abp-has(.sponsored)
example.com#?#:-abp-contains(ads)
```

---

### domain-redundancy

**Description**

Detects redundant domain declarations within a cosmetic rule or network filter option.

**Why this matters**

Duplicate or contradictory domain lists make the filter list harder to maintain. Redundant domains add no value and can lead to confusion about the filter's intended scope.

**Invalid**

```txt
example.com,example.org,example.com##.ads
*$domain=example.com|example.org|example.com
example.com,~example.com##.ads
```

**Valid**

```txt
example.com,example.org##.ads
*$domain=example.com|example.org
```

---

### domain-case

**Description**

Requires all domain names to be written in lowercase.

**Why this matters**

Domain names are case-insensitive. Using uppercase letters is unnecessary and violates style conventions in filter lists. Lowercasing domains is a standard practice for consistency.

**Invalid**

```txt
Example.com##.ads
*$domain=Example.com|X.COM
```

**Valid**

```txt
example.com##.ads
*$domain=example.com|x.com
```

---

### domain-syntax

**Description**

Checks for common syntax errors in domain declarations, such as empty domains, bad domain names (e.g., "a", "example."), domains with whitespace, and unsupported ancestor contexts (`>>`) in network filters.

**Why this matters**

Filter lists with these errors will not function as intended and can cause the entire list to fail.

**Invalid**

```txt
,example.com##.ads          // Empty domain before "example.com"
example.com,,example.org##.ads // Empty domain between
e xample.com##.ad           // Whitespace in domain
example.com>>##.ads         // Invalid ancestor context count
*$domain=example.com>>      // Ancestor context in network filter
```

**Valid**

```txt
example.com,example.org##.ads
example.com>>##.ads         // Cosmetic filter supports it
```

---

## Network Rules

These rules validate the syntax and options of network filters, which are used to block or allow network requests.

### duplicate-options

**Description**

Detects duplicate filter options within a single network filter.

**Why this matters**

Duplicate options are redundant and can cause confusion about which value is actually being applied. This often results from copy-pasting or editing errors.

**Invalid**

```txt
*$3p,script,3p
*$domain=a.com,domain=b.com
```

**Valid**

```txt
*$3p,script
*$domain=a.com|b.com
```

---

### option-case

**Description**

Requires all network filter options to be written in lowercase.

**Why this matters**

Filter options are case-sensitive and standard practice dictates using lowercase. Mixed-case options are a source of errors.

**Invalid**

```txt
*$SCRIPT,3p,Css
```

**Valid**

```txt
*$script,3p,css
```

---

### option-alias-redundancy

**Description**

Detects the use of both an alias and its canonical name in the same filter (e.g., `$css` and `$stylesheet`, `$from` and `$domain`).

**Why this matters**

Using aliases redundantly is unnecessary. It is generally preferred to use the canonical form for consistency.

**Invalid**

```txt
*$css,script,stylesheet
*$domain=example.com,from=example.com
```

**Valid**

```txt
*$stylesheet,script
*$domain=example.com
```

---

### denyallow-to-conflict

**Description**

Flags the redundant use of `$denyallow` in combination with `$to`.

**Why this matters**

`$denyallow` is a subset of what `$to` can do. The same effect can be achieved with an inverted `$to` list, making the `$denyallow` option redundant.

**Invalid**

```txt
*$script,denyallow=x.com,to=z.org
```

**Valid**

```txt
*$script,to=~x.com|z.org
```

---

### denyallow-requires-domain

**Description**

Checks that the `$denyallow` option is always accompanied by `$domain` or `$from`.

**Why this matters**

`$denyallow` is meaningless without a domain context. It is designed to refine domain-based rules.

**Invalid**

```txt
*$3p,script,denyallow=x.com
```

**Valid**

```txt
*$3p,script,denyallow=x.com,domain=a.com
```

---

### denyallow-value-syntax

**Description**

Enforces syntax rules for the values of the `$denyallow` option.

- Negated domains (`~`) are not allowed.
- Wildcard TLDs (`.*`) are not allowed.

**Why this matters**

These restrictions ensure that the `$denyallow` option is used precisely and effectively.

**Invalid**

```txt
*$domain=a.com,denyallow=~x.com
*$domain=a.com,denyallow=x.*
```

**Valid**

```txt
*$domain=a.com,denyallow=x.com|y.com
```

---

### option-conflict

**Description**

Detects when both a positive and a negated version of an option are used in the same filter (e.g., `$script` and `$~script`).

**Why this matters**

This is a logical contradiction that makes the filter invalid.

**Invalid**

```txt
*$script,~script
*$css,~stylesheet
```

**Valid**

```txt
*$script
*$~image
```

---

### invalid-option-negation

**Description**

Flags options that cannot be negated.

**Why this matters**

Some options (e.g., `$domain`, `$important`, `$cname`) are not boolean and cannot be negated. Attempting to do so is a syntax error.

**Invalid**

```txt
*$~domain=a.com
*$~strict1p
```

**Valid**

```txt
*$domain=~a.com
*$strict1p
```

---

### deprecated-options

**Description**

Warns when using deprecated filter options.

**Why this matters**

Deprecated options are no longer recommended and will be removed in future versions.

**Invalid**

```txt
||example.org^$empty
||example.com/videos/$mp4
```

**Valid**

```txt
||example.org^$redirect=empty
||example.com/videos/$media
```

---

### exception-options

**Description**

Validates the use of options in exception rules (`@@`).

- Certain options (e.g., `$important`) are not allowed in exceptions.
- Options that require a value (e.g., `$csp`) are only valid without a value in exceptions.
- Some options (e.g., `$cname`, `$genericblock`) are only allowed in exceptions.

**Why this matters**

Exception rules have specific behaviors that differ from block rules. Using options incorrectly can break the intended functionality.

**Invalid**

```txt
*$important             // Not allowed in block rules
@@*$cname               // $cname is only allowed in exceptions
*$csp                   // $csp must have a value in block rules
```

**Valid**

```txt
@@*$important           // Allowed in exceptions
@@*$cname               // Only allowed in exceptions
*$csp=foo               // Value required in block rules
@@*$csp                 // No value required in exceptions
```

---

### inter-option-domain-contradiction

**Description**

Detects contradictions between domain-related options (`$domain`, `$from`, `$to`, `$denyallow`).

**Why this matters**

Such contradictions make the filter's intended scope ambiguous or invalid.

**Invalid**

```txt
*$domain=a.com,denyallow=a.com
*$from=a.com,to=~a.com
```

**Valid**

```txt
*$domain=a.com,denyallow=b.com
*$from=a.com,to=a.com|b.com
```

---

### redirect-value-validity

**Description**

Validates the values used in `$redirect` and `$redirect-rule` options.

- Checks for invalid syntax.
- Warns about deprecated redirect resources.
- Flags unknown redirect resources and suggests possible alternatives.

**Why this matters**

Using invalid or unknown redirect resources will break the filter.

**Invalid**

```txt
*$redirect=invalid
*$redirect=ligatus_angular-tag.js
```

**Valid**

```txt
*$redirect=noopjs
*$redirect=google-ima.js:100
```

---

### unknown-options

**Description**

Flags any filter options that are not recognized by the linter.

**Why this matters**

Typos or unsupported options lead to inactive or broken filters.

**Invalid**

```txt
||example.com^$foo,css,bar
```

**Valid**

```txt
||example.com^$css,script
```

---

### pattern-anchors

**Description**

Validates the use of anchors (`|`) in network filter patterns.

- Ensures no more than two `|` at the beginning (for `||` hostname anchors).
- Ensures no more than one `|` at the end (for a trailing path anchor).

**Why this matters**

Incorrect anchor placement is a common source of errors, leading to filters that don't match as expected.

**Invalid**

```txt
|||https://example.com
@@|||example.com/js/pop.js||
```

**Valid**

```txt
||example.com
@@||example.com/js/pop.js|
```

---

## Preprocessor Rules

These rules validate preprocessor directives (`!#if`, `!#else`, `!#endif`) used in filter lists.

### if-closed

**Description**

Ensures that every `!#if` directive has a matching `!#endif` and that `!#else` directives are used correctly.

**Why this matters**

Unclosed or incorrectly nested preprocessor directives will break the parsing of the entire filter list.

**Invalid**

```txt
!#if ext_ubol
foo
```

**Valid**

```txt
!#if ext_ubol
foo
!#endif
```

---

### expression-syntax

**Description**

Validates the syntax and logic of `!#if` conditions.

- Checks for balanced parentheses.
- Reports unknown identifiers (e.g., `unknown_value`).
- Detects mutually exclusive identifiers (e.g., `adguard` and `ext_ublock`).
- Prevents nested exclusions that are impossible to satisfy.

**Why this matters**

Syntax errors in preprocessor conditions can cause entire sections of the filter list to be incorrectly included or excluded, breaking the functionality. Mutually exclusive conditions are redundant and can lead to dead code.

**Invalid**

```txt
!#if (              // Unclosed parenthesis
!#if unknown_value  // Unknown identifier
!#if adguard && ext_ublock // Mutually exclusive
```

**Valid**

```txt
!#if adguard
!#endif
!#if env_firefox
  ...            // Only applies in Firefox
!#endif
```

---

## Line Rules

These rules analyze the structure and properties of individual lines in the filter list.

### excessive-empty-lines

**Description**

Limits the number of consecutive empty lines. The maximum allowed can be configured.

**Why this matters**

Too many empty lines are unnecessary and bloat the file. This rule enforces consistent and clean formatting.

**Invalid**

```txt
foo

                    // 4 empty lines
bar
```

**Valid**

```txt
foo

                    // 2 empty lines (max 2)
bar
```

---

### too-short-rules

**Description**

Checks for filter rules that are too short. The minimum length can be configured.

**Why this matters**

Exceptionally short rules often are not precise enough to be useful, are typos, or could be overly broad.

**Invalid**

```txt
bar             // Less than 4 characters
foo$css,3p      // Pattern 'foo' is less than 4 characters
```

**Valid**

```txt
abcd            // 4 characters or more
!a              // Comment, always OK
```

---

## Redundancy Rules

These rules identify redundant filters that are fully covered by other filters in the same list. They work for both cosmetic and network filters.

### cosmetic-redundancy

**Description**

Detects cosmetic filters that are redundant because they are already covered by a more general or equivalent selector elsewhere in the list.

**Why this matters**

Redundant filters increase list size and maintenance effort without providing any additional blocking benefits. Removing them improves performance and maintainability.

**Invalid**

```txt
##.ads
example.com##.ads           // Covered by global ##.ads
##[class="ads"]             // Covered by ##.ads
```

**Valid**

```txt
##.ads
example.com##.banner         // Different selector
```

---

### network-redundancy

**Description**

Detects network filters that are redundant because they are fully covered by a more general or equivalent filter elsewhere.

**Why this matters**

Like its cosmetic counterpart, redundant network filters make the list harder to maintain and can negatively impact performance.

**Invalid**

```txt
/ads/*
||somesite.com/ads/      // Covered by /ads/*
adv$domain=~x.com        // Covered by adv
```

**Valid**

```txt
/ads/*
||somesite.com^          // Different pattern
```

---

## Scriptlet Rules

These rules validate the scriptlets used in `+js(...)` and similar directives.

### scriptlet-validity

**Description**

Validates the scriptlet name used in `+js(...)` or `#%#//scriptlet(...)` directives.

- Checks for valid and known scriptlet names.
- Flags deprecated scriptlets.
- Suggests alternatives for unknown scriptlets.

**Why this matters**

Using an unknown, misspelled, or deprecated scriptlet will break the filter's functionality.

**Invalid**

```txt
example.org##+js(nowolf)
example.org##+js(csp)
```

**Valid**

```txt
example.org##+js(noeval)
example.org##+js(nowoif)
```
