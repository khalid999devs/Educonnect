"use client";

import { UploadDropzone, type UploadStatus } from "@educonnect/ui";
import { useEffect, useRef, useState } from "react";

/**
 * Gallery-only driver that walks the dropzone through its real states with a
 * simulated transfer. Nothing is stored; the page labels it as a state demo.
 */
export function UploadDemo() {
  const [status, setStatus] = useState<UploadStatus>("idle");
  const [fileName, setFileName] = useState<string>();
  const [progress, setProgress] = useState(0);
  const timer = useRef<ReturnType<typeof setInterval>>(undefined);

  const stopTimer = () => {
    if (timer.current !== undefined) {
      clearInterval(timer.current);
      timer.current = undefined;
    }
  };

  useEffect(() => stopTimer, []);

  const startUpload = (name: string) => {
    setFileName(name);
    setProgress(0);
    setStatus("uploading");
    stopTimer();

    timer.current = setInterval(() => {
      setProgress((current) => {
        if (current >= 100) {
          stopTimer();
          setStatus("success");

          return 100;
        }

        return current + 10;
      });
    }, 250);
  };

  const reset = () => {
    stopTimer();
    setStatus("idle");
    setFileName(undefined);
    setProgress(0);
  };

  return (
    <div className="space-y-3">
      <UploadDropzone
        status={status}
        fileName={fileName}
        progress={status === "uploading" ? progress : undefined}
        errorMessage="The simulated connection dropped."
        acceptDescription="PDF, DOCX, or images"
        maxSizeDescription="Up to 25 MB per file"
        privacyNote="Only you can see your uploads."
        onFilesSelected={(files) =>
          startUpload(files[0]?.name ?? "document.pdf")
        }
        onCancel={reset}
        onRetry={() => startUpload(fileName ?? "document.pdf")}
        onReset={reset}
      />
      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          onClick={() => {
            stopTimer();
            setFileName((name) => name ?? "syllabus.pdf");
            setStatus("error");
          }}
          className="text-caption text-text-muted underline underline-offset-2 hover:text-text-primary"
        >
          Simulate a failed upload
        </button>
      </div>
    </div>
  );
}
