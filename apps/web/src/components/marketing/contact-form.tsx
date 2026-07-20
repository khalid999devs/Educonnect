"use client";

import {
  Alert,
  Button,
  FormField,
  Input,
  Select,
  Textarea,
} from "@educonnect/ui";
import { useState, type FormEvent } from "react";

import { submitContactMessage, type ContactTopic } from "@/lib/api/contact";
import { ApiError } from "@/lib/api/http";

const TOPICS: ReadonlyArray<{ value: ContactTopic; label: string }> = [
  { value: "product", label: "Product question" },
  { value: "privacy", label: "Privacy or data" },
  { value: "partnership", label: "University or mentor interest" },
  { value: "other", label: "Something else" },
];

/** The public contact form. Submits to the API, which emails the support inbox. */
export function ContactForm() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [topic, setTopic] = useState<ContactTopic>("product");
  const [message, setMessage] = useState("");
  const [isSending, setIsSending] = useState(false);
  const [isSent, setIsSent] = useState(false);
  const [apiError, setApiError] = useState<ApiError | null>(null);
  const [unexpectedError, setUnexpectedError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSending(true);
    setApiError(null);
    setUnexpectedError(null);

    try {
      await submitContactMessage({ name, email, topic, message });
      setIsSent(true);
      setName("");
      setEmail("");
      setTopic("product");
      setMessage("");
    } catch (error) {
      if (error instanceof ApiError) {
        setApiError(error);
      } else {
        setUnexpectedError(
          "The message could not be sent. Please try again in a moment.",
        );
      }
    } finally {
      setIsSending(false);
    }
  }

  return (
    <form
      aria-describedby="contact-form-status"
      onSubmit={handleSubmit}
      className="space-y-5"
    >
      <div id="contact-form-status" aria-live="polite">
        {isSent ? (
          <Alert variant="success" title="Message sent">
            <p>
              Thanks for reaching out. We read every message and reply by email.
            </p>
          </Alert>
        ) : null}
        {unexpectedError ? (
          <Alert variant="error" title="Could not send">
            <p>{unexpectedError}</p>
          </Alert>
        ) : null}
        {apiError && Object.keys(apiError.details).length === 0 ? (
          <Alert variant="error" title="Could not send">
            <p>{apiError.message}</p>
          </Alert>
        ) : null}
      </div>

      <div className="grid gap-5 sm:grid-cols-2">
        <FormField label="Name" required error={apiError?.fieldError("name")}>
          {(control) => (
            <Input
              {...control}
              autoComplete="name"
              value={name}
              onChange={(event) => setName(event.target.value)}
            />
          )}
        </FormField>
        <FormField label="Email" required error={apiError?.fieldError("email")}>
          {(control) => (
            <Input
              {...control}
              type="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
            />
          )}
        </FormField>
      </div>

      <FormField label="Topic" error={apiError?.fieldError("topic")}>
        {(control) => (
          <Select
            {...control}
            value={topic}
            onChange={(event) => setTopic(event.target.value as ContactTopic)}
          >
            {TOPICS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        )}
      </FormField>

      <FormField
        label="Message"
        required
        hint="Plain text, as much detail as you like."
        error={apiError?.fieldError("message")}
      >
        {(control) => (
          <Textarea
            {...control}
            value={message}
            onChange={(event) => setMessage(event.target.value)}
          />
        )}
      </FormField>

      <Button type="submit" isLoading={isSending} loadingLabel="Sending">
        Send message
      </Button>
    </form>
  );
}
