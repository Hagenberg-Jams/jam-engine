/**
 * A small stand-in for a Cloudflare R2 bucket binding, with the methods the functions use.
 */
export function createR2() {
    const objects = new Map();
    const uploads = new Map();

    const bytesOf = async (value) => {
        if (value === null || value === undefined) {
            return new Uint8Array();
        }
        if (typeof value === 'string') {
            return new TextEncoder().encode(value);
        }
        if (value instanceof Uint8Array) {
            return value;
        }
        if (value instanceof ArrayBuffer) {
            return new Uint8Array(value);
        }

        return new Uint8Array(await new Response(value).arrayBuffer());
    };

    const view = (key, entry) => ({
        key,
        size: entry.bytes.length,
        customMetadata: entry.customMetadata,
        httpMetadata: entry.httpMetadata,
        uploaded: entry.uploaded,
        text: async () => new TextDecoder().decode(entry.bytes),
        json: async () => JSON.parse(new TextDecoder().decode(entry.bytes)),
        arrayBuffer: async () => entry.bytes.buffer,
    });

    const multipart = (key, uploadId) => ({
        key,
        uploadId,
        async uploadPart(partNumber, value) {
            const upload = uploads.get(uploadId);
            if (!upload) {
                throw new Error('unknown upload');
            }
            upload.parts.set(partNumber, await bytesOf(value));

            return { partNumber, etag: `etag-${partNumber}` };
        },
        async complete(parts) {
            const upload = uploads.get(uploadId);
            const chunks = parts.map((part) => upload.parts.get(part.partNumber) ?? new Uint8Array());
            const bytes = new Uint8Array(chunks.reduce((sum, chunk) => sum + chunk.length, 0));
            let offset = 0;
            for (const chunk of chunks) {
                bytes.set(chunk, offset);
                offset += chunk.length;
            }
            objects.set(key, { bytes, customMetadata: upload.customMetadata, httpMetadata: upload.httpMetadata, uploaded: new Date() });
            uploads.delete(uploadId);

            return view(key, objects.get(key));
        },
        async abort() {
            uploads.delete(uploadId);
        },
    });

    return {
        objects,
        uploads,
        async put(key, value, options = {}) {
            objects.set(key, { bytes: await bytesOf(value), customMetadata: options.customMetadata ?? {}, httpMetadata: options.httpMetadata ?? {}, uploaded: new Date() });

            return view(key, objects.get(key));
        },
        async get(key) {
            return objects.has(key) ? view(key, objects.get(key)) : null;
        },
        async head(key) {
            return objects.has(key) ? view(key, objects.get(key)) : null;
        },
        async delete(key) {
            objects.delete(key);
        },
        async list({ prefix = '' } = {}) {
            const found = [...objects.keys()].filter((key) => key.startsWith(prefix)).sort().map((key) => view(key, objects.get(key)));

            return { objects: found, truncated: false };
        },
        async createMultipartUpload(key, options = {}) {
            const uploadId = `upload-${uploads.size + 1}-${Math.random().toString(36).slice(2)}`;
            uploads.set(uploadId, { key, parts: new Map(), customMetadata: options.customMetadata ?? {}, httpMetadata: options.httpMetadata ?? {} });

            return multipart(key, uploadId);
        },
        resumeMultipartUpload(key, uploadId) {
            return multipart(key, uploadId);
        },
    };
}
