<p>Handlers declare their contract in a docblock:</p>
<pre>
/**
 * @bidirekt provides GET /health
 * @bidirekt response 200
 */
</pre>
<?php

/**
 * @bidirekt provides GET /pets
 * @bidirekt response 200
 */
function listPets(): void {}
