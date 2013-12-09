## Shared storage

Simple shared storage implementation using single shared memory segment.  
For storing small data payloads.

#### Setting and getting data 

```php
<?php

use Shared\Storage;

$s = new Storage();
$s->set('name', 'Jango');

// Will be available in next script executions
$s->get('name');
```
