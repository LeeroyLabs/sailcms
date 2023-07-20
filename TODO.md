TODO before v3.0.0 is complete

- Navigation
    - Tooling for easy access via graphql or php api
    - Multi-level
    - Supports
        - Links
        - External Links
        - Content (any type)

- Model
    - ✅ Use fields variable to declare the fields
    - ✅ Use guard variable to declare what fields not to show in json
    - ✅ Use collection variable to declare what collection to use, if not set, use class name
    - ✅ Use casting variable to declare what variable are to be casted into and from to what type of class
    - ✅ Use connection variable to declare what db connection to use (defaults to the primary)
    - ✅ Remove the need to define every object property in advance, use phpdoc for ide help
    - ✅ Use validators to enable validation on given fields for built in validators or custom validators
        - ✅ Support not-empty, string, numeric and boolean out of the box
        - ✅ Support for custom validators
    - ✅ Add a 'Validator' contract for validating fields
    - ✅ Support automatic casting for stdClass but require implementation of Castable for other objects
        - ✅ Collection supported (supported when typing Collection::class as cast)
        - ✅ Carbon (supported when typing Carbon::class as cast)
        - ✅ DateTime (supported when typing DateTime::class as cast)
            - ✅ Encrypted (supported when typing encrypted as cast, works only on strings)
    - ✅ Unit Test all new changes

- SEO
    - Robots.txt
    - Sitemap.xml
    - ✅ Social Headers
    - ✅ Meta Tags
    - ✅ Override title
    - ✅ Alternates

- Versioning
    - Preview version
    - ✅ Store version on save
    - ✅ List/Restore/Delete versions

- Search
    - Algolia adapter (as package)