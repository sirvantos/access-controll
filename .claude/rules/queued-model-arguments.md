# Queued Model Arguments

- When dispatching queued Jobs, queued listeners, or Spatie Queueable Actions, prefer passing resolved Eloquent models over model identifiers when the caller already has the model instance.
- Rely on Laravel's queue model serialization to store the identifier and restore the model for execution.
- Use a model ID only when the caller genuinely does not have the model instance, or when the queued work intentionally needs an identifier snapshot rather than a restored model.
