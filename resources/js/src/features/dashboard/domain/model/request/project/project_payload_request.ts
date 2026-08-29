export interface CreateProjectRequest {
    name: string;
    type: string;
    slug: string;
    callback: string;
}

export interface UpdateProjectRequest {
    name?: string;
    type?: string;
    slug?: string;
    callback?: string;
}
