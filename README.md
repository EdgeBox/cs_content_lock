The CS Content Lock module extends the functionality of Content Sync by enabling editors to lock content on one site, preventing it from being edited on another site.

To activate the lock functionality for a specific content type, you need to add a plain textfield to the entity bundle that you want to be able to lock. The field MUST be named "field_content_sync_content_lock".
This field must NOT be manually editable by editors and should be hidden everywhere.

In order for users to have the ability to lock and unlock content, they must be granted the newly added permission called "Lock and unlock content".

Content locking can be performed directly from the content overview page by using the available actions for each content item. Once a piece of content is locked on one site, it becomes uneditable on any other site.

Please note that the administrator (User 1) will still retain the ability to access the content edit page on all sites.

To enhance the user experience, it is highly recommended to include the "Sync State + Lock" field in the Drupal content overview view page. This field will provide users with information about the synchronization status of the entity as well as its current lock state.

To avoid redundancy, we recommend setting the Pull flows update behavior to "forbid local changes."
