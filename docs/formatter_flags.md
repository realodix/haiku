The formatter is opinionated and ships sensible defaults, but a handful of options let you tune it to a different house style. Everything sits under the `formatter.flags`.

```yml
formatter:
  flags:
    option_format: short
    remove_wildcard_covered_domains: true
```


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


### # `combine_option_sets`

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


### # `option_format`

Normalizes network filter option names into a consistent format.

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


### # `option_order`

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


### # `remove_subdomains`

**Default**: `false`

**Possible values**: `true`, `false`

Removes subdomain entries that are covered by their parent domain. When a base domain like `example.com` is present, subdomains like `api.example.com` are redundant. Negated subdomains are preserved.

```adblock
!## BEFORE
example.com,~ads.example.com,api.example.com,example.org##.ads

!## AFTER
~ads.example.com,example.com,example.org##.ads
```


### # `remove_wildcard_covered_domains`

**Default**: `false`

**Possible values**: `true`, `false`

Eliminates explicit domains that are already covered by wildcard TLD domains. When a wildcard domain like `example.*` is present, specific domains like `example.com` are redundant and removed. Negated domains are preserved.

```adblock
!## BEFORE
example.com,~example.net,example.*##.ads

!## AFTER
~example.net,example.*##.ads
```


### # `adg_non_basic_rule_modifier`

**Default**: `false`

**Possible values**: `true`, `false`

```adblock
!## BEFORE
[$domain=b.com|a.com,app=test_app|com.apple.Safari]##selector

!## AFTER
[$app=com.apple.Safari|test_app,domain=a.com|b.com]##selector
```
