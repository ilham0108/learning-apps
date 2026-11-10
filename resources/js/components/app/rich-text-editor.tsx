import Link from '@tiptap/extension-link';
import { EditorContent, useEditor } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { LucideIcon } from 'lucide-react';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Undo2,
} from 'lucide-react';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    value: string;
    onChange: (html: string) => void;
    invalid?: boolean;
    ariaLabel: string;
};

/**
 * Editor teks kaya untuk artikel. HTML yang dihasilkan tetap disanitasi
 * di server; editor hanya menyediakan format yang juga diizinkan server.
 */
export function RichTextEditor({
    id,
    value,
    onChange,
    invalid = false,
    ariaLabel,
}: Props) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3, 4] } }),
            Link.configure({
                openOnClick: false,
                protocols: ['http', 'https', 'mailto'],
            }),
        ],
        content: value,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                id,
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': ariaLabel,
                class: 'prose prose-sm dark:prose-invert max-w-none min-h-48 px-3 py-2 focus:outline-none',
            },
        },
        onUpdate: ({ editor: current }) =>
            onChange(current.isEmpty ? '' : current.getHTML()),
    });

    return (
        <div
            className={cn(
                'rounded-md border shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50',
                invalid && 'border-destructive',
            )}
        >
            {editor && <Toolbar editor={editor} />}
            <EditorContent editor={editor} />
        </div>
    );
}

function Toolbar({ editor }: { editor: Editor }) {
    const setLink = () => {
        const previous = editor.getAttributes('link').href as
            | string
            | undefined;
        const url = window.prompt(
            t('editor.linkPrompt'),
            previous ?? 'https://',
        );

        if (url === null) {
            return;
        }

        if (url.trim() === '') {
            editor.chain().focus().unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url.trim() })
            .run();
    };

    const buttons: {
        label: string;
        icon: LucideIcon;
        action: () => void;
        active?: boolean;
    }[] = [
        {
            label: t('editor.bold'),
            icon: Bold,
            action: () => editor.chain().focus().toggleBold().run(),
            active: editor.isActive('bold'),
        },
        {
            label: t('editor.italic'),
            icon: Italic,
            action: () => editor.chain().focus().toggleItalic().run(),
            active: editor.isActive('italic'),
        },
        {
            label: t('editor.heading2'),
            icon: Heading2,
            action: () =>
                editor.chain().focus().toggleHeading({ level: 2 }).run(),
            active: editor.isActive('heading', { level: 2 }),
        },
        {
            label: t('editor.heading3'),
            icon: Heading3,
            action: () =>
                editor.chain().focus().toggleHeading({ level: 3 }).run(),
            active: editor.isActive('heading', { level: 3 }),
        },
        {
            label: t('editor.bulletList'),
            icon: List,
            action: () => editor.chain().focus().toggleBulletList().run(),
            active: editor.isActive('bulletList'),
        },
        {
            label: t('editor.orderedList'),
            icon: ListOrdered,
            action: () => editor.chain().focus().toggleOrderedList().run(),
            active: editor.isActive('orderedList'),
        },
        {
            label: t('editor.quote'),
            icon: Quote,
            action: () => editor.chain().focus().toggleBlockquote().run(),
            active: editor.isActive('blockquote'),
        },
        {
            label: t('editor.link'),
            icon: Link2,
            action: setLink,
            active: editor.isActive('link'),
        },
        {
            label: t('editor.undo'),
            icon: Undo2,
            action: () => editor.chain().focus().undo().run(),
        },
        {
            label: t('editor.redo'),
            icon: Redo2,
            action: () => editor.chain().focus().redo().run(),
        },
    ];

    return (
        <div
            className="flex flex-wrap gap-1 border-b p-1"
            role="toolbar"
            aria-label={t('editor.toolbar')}
        >
            {buttons.map(({ label, icon: Icon, action, active }) => (
                <button
                    key={label}
                    type="button"
                    onClick={action}
                    aria-label={label}
                    title={label}
                    aria-pressed={active}
                    className={cn(
                        'inline-flex size-8 items-center justify-center rounded hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        active && 'bg-muted text-primary',
                    )}
                >
                    <Icon className="size-4" aria-hidden="true" />
                </button>
            ))}
        </div>
    );
}
