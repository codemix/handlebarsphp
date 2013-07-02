# HandlebarsPHP

Turns handlebars templates into executable PHP code ahead of time, ships with built in support for Yii 1.x.
but allows customisation of output to support different frameworks.

# Installation

Install via composer, package name is `codemix/handlebarsphp`.

# Differences from HandlebarsJS

HandlebarsPHP aims to be compatible with a subset of HandlebarsJS. For performance reasons it compiles the templates
ahead of time and this necessitates some restrictions.

1. Since it's impossible to tell whether a tag like `{{foo}}` is a helper or a property at compile time, we assume
that its a property. We differentiate helpers by looking for arguments, for example `{{foo 1}}` will be interpretted
as a function call because it has an argument. This means that it is impossible to call a helper with zero arguments.

2. `{{@key}}` and `{{@index}}` always point to the same variable, because `foreach` is used for all iteration.





