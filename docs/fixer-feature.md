### Sorting & Deduplication

**Sorting**
```adblock
! before
example.com##+js(aopw, Fingerprint2)
example.com##.ads3
||example.com^$script
-banner-$image,domain=example.org
##.ads2
[$path=/page.html]example.com,0.0.0.0##.ads1

! after
-banner-$image,domain=example.org
||example.com^$script
##.ads2
example.com##.ads3
[$path=/page.html]0.0.0.0,example.com##.ads1
example.com##+js(aopw, Fingerprint2)
```

```adblock
! before
*$image,domain=b.com|a.com
b.com,a.com##.ads
[$path=/page.html,domain=b.com|a.com]##.textad

! after
*$image,domain=a.com|b.com
a.com,b.com##.ads
[$domain=a.com|b.com,path=/page.html]##.textad
```

```adblock
! before
*$image,css,script,doc

! after
*$css,doc,image,script
```

**Remove Duplicates**
```adblock
! before
*$css,image
*$image
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


### Rule Combining

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


### Normalization & Cleanup

**Lowercase**
```adblock
! before
*$IMAGE
EXAMPLE.COM##.ad

! after
*$image
example.com##.ad
```

**Trim Whitespace**
```adblock
! before
    ##.ads

! after
##.ads
```

### Typo

**Superfluous Separators**
```adblock
! before
/ads/*$image,domain=|example.com||example.org|
,example.com,,example.org,##.ads

! after
/ads/*$image,domain=example.com|example.org
example.com,example.org##.ads
```

**Domain Symbol**
```adblock
! before
example.com/##.ads1
.example.org##.ads2

! after
example.com##.ads1
example.org##.ads2
```
