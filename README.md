JMSTranslationBundle fork
=========================

Differences:

- Command performance upgrade: scan files only once and not per languages!
- Sort source
- Collect the placeholders (`<jms:placeholder>` element)
- Move meaning to `jms:meaning` attribute
- Custom translated form fields (You can set these in the `jms_translation` --> `custom_form_config_names` config place

```yml
jms_translation:
    custom_form_config_names:
        - 'title'
        - 'checkbox_label'
        - 'minMessage'
        - 'maxMessage'
        - 'help'
        - 'button_label'
        - 'autolock'
```

- Handle `addViolation()`, `addViolationAt()` and `buildViolation()` functions
- Add new Annotation: `AltTrans` . You can add basic translations:

```php
<?php

/**
 * @AltTrans("User has been created: <a href=""mailto:%email%"">%email%</a>", locale="en")
 * @AltTrans("A felhasználó létre lett hozva: <a href=""mailto:%email%"">%email%</a>", locale="hu")
 */
$this->trans('user.create.success.%email%', ['%email%' => $user->getEmail()]);
```

In twig template:

```twig
<input type="text" id="username" name="_username" placeholder="{{ 'form.username'
    | trans()
    | altTrans('en', 'Username')
    | altTrans('hu', 'Felhasználónév')
}}" />
```

> The double `""` sign is the escaped `"` in the `AltTrans` annotation value.

- Add new Trans* Annotations: `TransArrayKeys`, `TransArrayValues`, `TransString` . You can handle the strings in var:

```php
<?php

/** @TransArrayValues("error") */
$msgs = [
    /** @AltTrans("Error 1", locale="en") */
    'error.msg1',
    /** @AltTrans("Error 2", locale="en") */
    'error.msg2',
    /** @AltTrans("Error 3", locale="en") */
    'error.msg3',
];
/** @Ignore */
$this->trans($msgs[$errorId], [], "error");
```

JMSTranslationBundle [![Build Status](https://secure.travis-ci.org/schmittjoh/JMSTranslationBundle.png?branch=master)](http://travis-ci.org/schmittjoh/JMSTranslationBundle) [![Join the chat at https://gitter.im/schmittjoh/JMSTranslationBundle](https://badges.gitter.im/schmittjoh/JMSTranslationBundle.svg)](https://gitter.im/schmittjoh/JMSTranslationBundle?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
====================

Documentation: 
[Resources/doc](http://jmsyst.com/bundles/JMSTranslationBundle)
    

Code License:
[Resources/meta/LICENSE](https://github.com/schmittjoh/JMSTranslationBundle/blob/master/Resources/meta/LICENSE)


Documentation License:
[Resources/doc/LICENSE](https://github.com/schmittjoh/JMSTranslationBundle/blob/master/Resources/doc/LICENSE)
