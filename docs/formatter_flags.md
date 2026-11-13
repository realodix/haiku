The formatter is opinionated and ships sensible defaults, but a handful of options let you tune it to a different house style. Everything sits under the `formatter.flags`.

```yml
formatter:
  flags:
    all: true
    option_syntax: short
```

| Name | Default | `all: true` |
|------|---------|-----|
| [`attr_to_basic_selector`](#-attr_to_basic_selector)  | unset | `strict` |
| [`convert_abp_ext_selectors`](#-convert_abp_ext_selectors) | `false` | `true` |
| [`convert_legacy_remove_action`](#-convert_legacy_remove_action) | `false` | `true` |
| [`fix_domain_separators`](#-fix_domain_separators) | `false` | `true` |
| [`merge_domains`](#-merge_domains) | `true` | same as default |
| [`merge_options`](#-merge_options) | `true` | same as default |
| [`migrate_deprecated_options`](#-migrate_deprecated_options) | `false` | `true` |
| [`option_syntax`](#-option_syntax) | unset | `native` |
| [`remove_empty_lines`](#-remove_empty_lines) | `keep_before_comment` | same as default |
| [`remove_redundant_domains`](#-remove_redundant_domains) | `false` | `true` |
| [`sort_domains`](#-sort_domains) | `true` | same as default |
| [`sort_options`](#-sort_options) | `type` | same as default |
| [`sort_rules`](#-sort_rules) | `{cosmetic_by_selector: true}` | same as default |


### # `all`

Enable or disable all flags.

**Default**: unset

**Possible values**: `true`, `false`


### # `attr_to_basic_selector`

Converts `id` and `class` attribute selectors into their equivalent CSS basic selector forms. Other attribute operators such as `^=`, `$=`, `*=` are not modified.

**Default**: unset; `strict` when `all` is set to `true`.

**Possible values**:
- `strict`: Only applies transformations that are fully semantically equivalent.
- `loose`: Applies additional normalization that may broaden the match set.

```adblock
!## BEFORE
example.com##div[class="foo"] div[class~="bar"]

!## AFTER - strict
example.com##div[class="foo"] div.bar

!## AFTER - loose
example.com##div.foo div.bar
```


### # `convert_abp_ext_selectors`

Converts Adblock Plus extended selector syntax to native CSS selector.

**Default**: `false`

**Possible values**: `true`, `false`

**Supported**: `:-abp-contains()`, `:-abp-has()`

```adblock
!## BEFORE
example.com#?#div:-abp-has(> span:-abp-contains(Advertisment))

!## AFTER
example.com##div:has(> span:has-text(Advertisment))
```


### # `convert_legacy_remove_action`

**Default**: `false`

**Possible values**: `true`, `false`

```adblock
!## BEFORE
example.com##.banner { remove: true; }

!## AFTER
example.com##.banner:remove()
```


### # `fix_domain_separators`

Corrects incorrect separator usage:
- `|` is used for network rule domain lists
- `,` is used for cosmetic rule domain lists

**Default**: `false`

**Possible values**: `true`, `false`

```adblock
!## BEFORE
-ads-$domain=a.com,b.com,css
example.com|example.org##.ads

!## AFTER
-ads-$css,domain=a.com|b.com
example.com,example.org##.ads
```


### # `merge_domains`

**Default**: `true`

**Possible values**: `true`, `false`

```adblock
!## BEFORE
/ads/*$domain=example.com
/ads/*$domain=example.org
example.com##.ads
example.org##.ads

!## AFTER
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
```


### # `merge_options`

**Default**: `true`

**Possible values**: `true`, `false`

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


### # `migrate_deprecated_options`

Converts deprecated filter options to their modern equivalents.

**Default**: `false`

**Possible values**: `true`, `false`

**Supported options**: `$empty`, `$mp4`,`$object-subrequest`, `$queryprune`.

```adblock
!## BEFORE
*$queryprune=utm_source

!## AFTER
*$removeparam=utm_source
```


### # `option_syntax`

Network filter option should be standardised using configured syntax.

**Default**: unset; `native` when `all` is set to `true`.

**Possible values**:
- `long`: Converts all options to their descriptive (long) form.
- `short`: Converts all options to their abbreviated (short) form.
- `native`: Converts all options to their native form.

```adblock
!## BEFORE
*$1p,document

!## AFTER - long
*$first-party,document

!## AFTER - short
*$1p,doc

!## AFTER - native
*$~third-party,document
```


### # `remove_empty_lines`

**Default**: `keep_before_comment`

**Possible values**:
- `true`: Remove empty lines.
- `keep_before_comment`: Remove empty lines, except when the next line is a comment.
- `false`: Disable this rule.

```adblock
!## BEFORE
##.ads

##.banner

! comment
##.foo
```

```adblock
!## AFTER - true
##.ads
##.banner
! comment
##.foo


!## AFTER - keep_before_comment
##.ads
##.banner

! comment
##.foo
```


### # `remove_redundant_domains`

**Configuration**:
- `subdomains`

  Removes subdomain that are covered by their parent domain.

  Default value: `false`

- `wildcard_covered`

  Remove domains covered by domains with a wildcard TLD.

  Default value: `false`

```adblock
! remove_redundant_domains:
!   subdomains: true
!## BEFORE
example.com,~ads.example.com,api.example.com,example.org##.ads

!## AFTER
~ads.example.com,example.com,example.org##.ads


! remove_redundant_domains:
!   wildcard_covered: true
!## BEFORE
example.com,~example.net,example.*##.ads

!## AFTER
~example.net,example.*##.ads
```


### # `sort_domains`

**Default**: `true`

**Possible values**: `true`, `false`

```adblock
!## BEFORE
*$css,domain=a.com|c.com|b.com
b.com,a.com,c.com##.ads

!## AFTER
*$css,domain=a.com|b.com|c.com
a.com,b.com,c.com##.ads
```


### # `sort_options`

**Default**: `type`

**Possible values**:
- `name`: Sort options alphabetically by name.
- `type`: Options are grouped by type. The group order is fixed, and options inside each group are sorted alphabetically.
- `false`: Disable this rule.

```adblock
!## BEFORE
*$image,script,css,badfilter,domain=example.com
-ads-$domain=~example.com,~image,third-party,xmlhttprequest,reason=disreputable

!## AFTER - name
*$badfilter,css,domain=example.com,image,script
-ads-$domain=~example.com,~image,reason=disreputable,third-party,xmlhttprequest

!## AFTER - type
*$badfilter,css,image,script,domain=example.com
-ads-$third-party,~image,xmlhttprequest,domain=~example.com,reason=disreputable
```


### # `sort_rules`

**Default**: `{cosmetic_by_selector: true}`

**Possible values**:
- `{cosmetic_by_selector: bool}`:
  - `true`: Sort cosmetic rules by selector.
  - `false`: Sort cosmetic rules by their original line order.
- `false`: Disable this rule.

```adblock
!## BEFORE
/ads2.$3p
/ads1.$3p
2.example.com##.ads2
3.example.com##.ads1
1.example.com##.ads3

!## AFTER
! sort_rules:
!   cosmetic_by_selector: true
/ads1.$3p
/ads2.$3p
3.example.com##.ads1
2.example.com##.ads2
1.example.com##.ads3

!## AFTER
! sort_rules:
!   cosmetic_by_selector: false
/ads1.$3p
/ads2.$3p
3.example.com##.ads1
2.example.com##.ads2
1.example.com##.ads3
```


<!-- ### # `adg_non_basic_rule_modifier`

**Default**: `false`

**Possible values**: `true`, `false`

```adblock
!## BEFORE
[$domain=b.com|a.com,app=test_app|com.apple.Safari]##selector

!## AFTER
[$app=com.apple.Safari|test_app,domain=a.com|b.com]##selector
``` -->
