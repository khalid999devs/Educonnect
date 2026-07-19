"use client";

import {
  Alert,
  Button,
  FormField,
  Input,
  Select,
  Textarea,
} from "@educonnect/ui";

/**
 * The real contact form UI, shipped before a support inbox exists. Sending
 * stays disabled and says so - no fake submission, no invented addresses.
 */
export function ContactForm() {
  return (
    <form
      aria-describedby="contact-form-status"
      onSubmit={(event) => event.preventDefault()}
      className="space-y-5"
    >
      <Alert variant="info" title="Sending activates at public launch">
        <p id="contact-form-status">
          A monitored support inbox opens with registration. Until then this
          form is a preview and does not send anywhere.
        </p>
      </Alert>

      <div className="grid gap-5 sm:grid-cols-2">
        <FormField label="Name" required>
          {(control) => <Input autoComplete="name" {...control} />}
        </FormField>
        <FormField label="Email" required>
          {(control) => (
            <Input type="email" autoComplete="email" {...control} />
          )}
        </FormField>
      </div>

      <FormField label="Topic">
        {(control) => (
          <Select defaultValue="product" {...control}>
            <option value="product">Product question</option>
            <option value="privacy">Privacy or data</option>
            <option value="partnership">University or mentor interest</option>
            <option value="other">Something else</option>
          </Select>
        )}
      </FormField>

      <FormField label="Message" hint="Plain text, as much detail as you like.">
        {(control) => <Textarea {...control} />}
      </FormField>

      <Button type="submit" disabled>
        Send message (available at launch)
      </Button>
    </form>
  );
}
