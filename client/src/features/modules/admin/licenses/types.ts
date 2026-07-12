export type LicenseAddFormState = {
  project_name: string
  domain: string
  start_date: string
  expiry_date: string
  logo_url: string
  status: string
}

export type LicenseEditFormState = {
  project_name: string
  domain: string
  expiry_date: string
  logo_url: string
  status: string
}

export const defaultLicenseAddForm = (): LicenseAddFormState => ({
  project_name: "",
  domain: "",
  start_date: new Date().toISOString().slice(0, 10),
  expiry_date: "",
  logo_url: "",
  status: "active",
})
