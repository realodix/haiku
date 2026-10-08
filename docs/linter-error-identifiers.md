Every error reported by Haiku is associated with an error identifier. The identifier is printed alongside each error in the analysis output:

```sh
------ ----------------------------------------------------------------------------------------------------
Line   rules.txt
------ ----------------------------------------------------------------------------------------------------
:2     Redundant filter: ##[class="ads"] is redundant due to more general selector on line 1
        cosmetic.redundant
        ✏️  rules.txt:2
        ✏️  rules.txt:1

:4     Duplicate domain: example.com
        domain.duplicate
        ✏️  rules.txt:4
```

## Ignoring errors using identifiers

Error identifiers can be used to ignore specific errors. You can ignore errors in your configuration file using the identifier key:

```yml
linter:
  ignoreErrors:
    - identifier: cosmetic.redundant
```

Or with the identifiers key to ignore multiple identifiers at once:

```yml
linter:
  ignoreErrors:
    - identifiers:
      - cosmetic.redundant
      - domain.duplicate
```

## All error identifiers

```
extraBlankLines


net.duplicate
net.redundant
netPattern.tooShort
netPattern.tooManyLeftAnchors
netPattern.tooManyRightAnchors


cosmetic.duplicate
cosmetic.redundant
cosmetic.abpExtInvalid
selector.idStartsWithNumber
selector.colonNotEscaped
selector.colonTooManyBackslash


scriptlet.invalidValue
scriptlet.deprecated


domain.invalid
domain.invalidAncestorContext
domain.duplicate
domain.redundant
domain.conflict
domain.case
domain.empty
domain.singleChar
domain.whitespace
domain.malformed
domain.misplacedWildcard


option.missingMarker
option.multipleMarkers
option.invalid
option.case
option.duplicate
option.negated
option.deprecated

option.invalidInException
option.exceptionOnly
option.withoutValue

denyallow.missingDomainOption
denyallow.negatedDomain
denyallow.usedWithTo
denyallow.wildcard
redirect.invalidValue
redirect.deprecated


if.alwaysFalse
if.elseCondition
if.elseWithoutIf
if.empty
if.endifWithoutIf
if.extraClosingParenthesis
if.multipleElse
if.unclosed
if.unclosedOpeningParenthesis
if.invalidValue
```
