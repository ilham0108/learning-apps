import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/app/form-field';
import { OptionSelect } from '@/components/app/option-select';
import { PageHeader } from '@/components/app/page-header';
import { RichTextEditor } from '@/components/app/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import materials from '@/routes/admin/materials';
import programs from '@/routes/admin/programs';
import type { MaterialContent, MaterialType } from '@/types';

type Props = {
    program: { id: string; name: string };
    module: { id: string; title: string };
    material:
        | (MaterialContent & {
              id: string;
              title: string;
              type: MaterialType;
              is_required: boolean;
              is_archived: boolean;
          })
        | null;
    initialType: MaterialType;
};

const types: MaterialType[] = [
    'article',
    'pdf',
    'image',
    'video_embed',
    'video_file',
    'link',
];
const fileTypes: MaterialType[] = ['pdf', 'image', 'video_file'];
const accept: Partial<Record<MaterialType, string>> = {
    pdf: 'application/pdf,.pdf',
    image: 'image/jpeg,image/png,image/webp',
    video_file: 'video/mp4,.mp4',
};

type FormData = {
    title: string;
    type: MaterialType;
    is_required: boolean;
    body_html: string;
    external_url: string;
    file: File | null;
};

export default function MaterialForm({
    program,
    module,
    material,
    initialType,
}: Props) {
    const form = useForm<FormData>({
        title: material?.title ?? '',
        type: material?.type ?? initialType,
        is_required: material?.is_required ?? true,
        body_html: material?.body_html ?? '',
        external_url: material?.external_url ?? '',
        file: null,
    });
    const usesFile = fileTypes.includes(form.data.type);
    const title = material
        ? t('admin.programs.material.editTitle')
        : t('admin.programs.material.createTitle');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            body_html: data.type === 'article' ? data.body_html : null,
            external_url:
                data.type === 'link' || data.type === 'video_embed'
                    ? data.external_url
                    : null,
            file: usesFile ? data.file : null,
        }));
        form.submit(
            material
                ? materials.update(material.id)
                : materials.store(module.id),
            { forceFormData: true },
        );
    };

    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={`${program.name} · ${module.title}`}
                />

                {material?.is_archived && (
                    <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                        {t('admin.programs.material.archivedNotice')}
                    </p>
                )}

                <Card>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-6">
                            <FormField
                                id="title"
                                label={t('admin.programs.material.title')}
                                error={form.errors.title}
                            >
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                            </FormField>

                            <FormField
                                id="type"
                                label={t('admin.programs.material.type')}
                                error={form.errors.type}
                                description={
                                    material
                                        ? t(
                                              'admin.programs.material.typeLocked',
                                          )
                                        : undefined
                                }
                            >
                                {material ? (
                                    <Input
                                        id="type"
                                        value={t(
                                            `status.materialType.${material.type}`,
                                        )}
                                        readOnly
                                    />
                                ) : (
                                    <OptionSelect
                                        id="type"
                                        value={form.data.type}
                                        onChange={(value) =>
                                            form.setData(
                                                'type',
                                                (value ??
                                                    'article') as MaterialType,
                                            )
                                        }
                                        options={types.map((type) => ({
                                            value: type,
                                            label: t(
                                                `status.materialType.${type}`,
                                            ),
                                        }))}
                                    />
                                )}
                            </FormField>

                            {form.data.type === 'article' && (
                                <FormField
                                    id="body_html"
                                    label={t('admin.programs.material.body')}
                                    error={form.errors.body_html}
                                >
                                    <RichTextEditor
                                        id="body_html"
                                        value={form.data.body_html}
                                        onChange={(html) =>
                                            form.setData('body_html', html)
                                        }
                                        invalid={
                                            form.errors.body_html !== undefined
                                        }
                                        ariaLabel={t(
                                            'admin.programs.material.body',
                                        )}
                                    />
                                </FormField>
                            )}

                            {(form.data.type === 'link' ||
                                form.data.type === 'video_embed') && (
                                <FormField
                                    id="external_url"
                                    label={t('admin.programs.material.url')}
                                    error={form.errors.external_url}
                                    description={
                                        form.data.type === 'video_embed'
                                            ? t(
                                                  'admin.programs.material.videoUrlHelp',
                                              )
                                            : undefined
                                    }
                                >
                                    <Input
                                        id="external_url"
                                        type="url"
                                        inputMode="url"
                                        value={form.data.external_url}
                                        onChange={(event) =>
                                            form.setData(
                                                'external_url',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'admin.programs.material.urlPlaceholder',
                                        )}
                                        required
                                    />
                                </FormField>
                            )}

                            {usesFile && (
                                <FormField
                                    id="file"
                                    label={t('admin.programs.material.file')}
                                    error={form.errors.file}
                                    description={[
                                        t(
                                            `admin.programs.material.fileHelp.${form.data.type as 'pdf' | 'image' | 'video_file'}`,
                                        ),
                                        material?.file_name
                                            ? t(
                                                  'admin.programs.material.replaceFileHelp',
                                              )
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                >
                                    {material?.file_name && (
                                        <p className="text-sm">
                                            {material.file_url ? (
                                                <a
                                                    href={material.file_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-primary hover:underline"
                                                >
                                                    {t(
                                                        'admin.programs.material.currentFile',
                                                        {
                                                            name: material.file_name,
                                                        },
                                                    )}
                                                </a>
                                            ) : (
                                                t(
                                                    'admin.programs.material.currentFile',
                                                    {
                                                        name: material.file_name,
                                                    },
                                                )
                                            )}
                                        </p>
                                    )}
                                    <Input
                                        id="file"
                                        type="file"
                                        accept={accept[form.data.type]}
                                        onChange={(event) =>
                                            form.setData(
                                                'file',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                        required={!material?.file_name}
                                    />
                                    {form.progress && (
                                        <p
                                            className="text-sm text-muted-foreground"
                                            aria-live="polite"
                                        >
                                            {t(
                                                'admin.programs.material.uploading',
                                                {
                                                    percent: Math.round(
                                                        form.progress
                                                            .percentage ?? 0,
                                                    ),
                                                },
                                            )}
                                        </p>
                                    )}
                                </FormField>
                            )}

                            <div className="grid gap-2">
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="is_required"
                                        checked={form.data.is_required}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'is_required',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_required">
                                        {t('admin.programs.material.required')}
                                    </Label>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {t('admin.programs.material.requiredHelp')}
                                </p>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={programs.show(program.id)}>
                                        {t('common.cancel')}
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing && <Spinner />}
                                    {t('common.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MaterialForm.layout = {
    breadcrumbs: [{ title: t('admin.programs.title'), href: programs.index() }],
};
