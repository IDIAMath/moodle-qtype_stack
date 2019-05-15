## Security-map

In STACK 4.3 the new Maxima statement parser infrastruture transfers 
the old listings of identifiers that are forbidden for students or simply 
globally forbidden to a JSON file describing all identifiers of functions, 
variables, constants, and operators and features we attach to them. This
JSON file is being updated manually and through the data collected by 
the [census](Census.md) and can be extended freely to track new features 
as things progress. This documentation section describes what is currently 
being contained in that map.

### Units

Note that the security-map does not define identifiers that are units and 
based on the current design you should not declare them there. In 
the current form if we are in units mode an overlay will be placed on top 
of the security-map and that overlay will declare are identifers that are 
units `mg`, `K`,... as `constant: 's'` meaning that they are constants that
the student may use in their inputs as long as they are not declared 
forbidden through the forbidden words mechanism.

## Features currently tracked

### Security

In security features we typically use values `"s"`, `"t"`, and`"f"`. `"s"` 
declares that students may use this identifier in the specified way unless
specifically forbidden with the forbidden words mechanism. `"t"` means that 
by default only the author may use it but can specially allow it using 
the allowed words mechanism. `"f"` means that for whatever reason not even 
the author may use it.

`constant` is a feature declaring the security level at which a given 
identifier is to be considered as a constant and can thus be only red and 
never written to. This makes it so that you cannot write `%pi:4` but can 
use `%pi` otherwise.

`evflag` is a feature declaring the security level at which a given 
identifier is to be considered as an evaluation flag. Basically, wether 
it can be used as a suffix to a statement `sqrt(3),numer` or as a paramter 
to `ev(sqrt(3),numer=true)` and similar functions.

`function` is a feature declaring the security level at which a given 
identifier is to be considered as a function name and can thus be called.

`keyword` is a feature declaring the security level at which a given 
identifier is to be considered as a keyword that can be used one could use 
it to declare that students may not use specific types of loops or other
flow control features.

`operator` is a feature declaring the security level at which a given 
operator is usable at. By operator we mean anything from `^` and ` and ` 
to various brackets.

`variable` is a feature declaring the security level at which a given 
identifier is to be considered as a variable and can thus be both written
and red.

In addition to those we also declare certain identifiers with 
`globalyforbiddenfunction` or `globalyforbiddenvariable` in the cases where
we might want to handle attempts to use them in a more serious manner.


### Nouns

In the old system certain functions and operators were nounified to stop 
the CAS from evaluating them and thus allow simpler assessment of 
the original input form. For this we define features `nounfunction` and
`nounoperator` that have the name of the matching nounified identifier.
When processing student input functions and operators that have noun 
variants are nounified unless specially requested to be kept as is. In 
addition to the nounifying one may need to denounify and then we define
`nounoperatorfor` or `nounfunctionfor` at the noun end.

### Aliases

`int(foo,x)` is equivalent to `integrate(foo,x)` which leads to issues 
when using forbidden words and only forbidding `integrate` to deal with 
this we now declare aliases as features for various identifiers so that 
if you forbid one the others are also forbidden. We also use those aliases
to map similar statements to use the same alias thus increasing 
the likelihood of cache hits. Aliases like nouns are defined by usage 
type e.g. `aliasfunction` feature tells that if some one uses a function 
with this name it should be interpreted as a function call with the name 
of this feature. `aliasvariable` does the same for both constants and
variables. As it is often necessary to find all the aliases that point to
a given identifier we also define features `aliasfunctions` and 
`aliasvariables` that contain lists of such identifiers. Updating such 
lists should be left to tools that make sure they are correct.

Aliases often do not match the logical aliases present in the CAS instead
they tend to map things so that they point to the version that is 
shorttest to minimise space usage, e.g. while some migt think 
`int => integrate` here we rather have `integrate => int`.