<?php

namespace App\Exceptions;

/**
 * A retried submission that was already processed (same submission_key for
 * a kitchen round). Renders exactly like PosException (422) for the web; the
 * API catches it and answers the retry as a success instead, so a mobile
 * client that lost the first response can safely resend.
 */
class DuplicateSubmissionException extends PosException {}
