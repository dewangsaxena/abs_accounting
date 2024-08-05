/**
 * This module will define the network API client.
 */
import axios from "axios";
import { getAttributeFromSession } from "../shared/functions";
import { APP_HOST, APP_VERSION } from "../shared/config";

/* Header Condfiguration */
axios.defaults.headers.post["Content-Type"] =
  "application/x-www-form-urlencoded";

/* Axios Instance */
const axiosInstance = axios.create({
  baseURL: APP_HOST,
});

// API Response
export interface APIResponse<T = void> {
  status: boolean;
  message?: string;
  data?: T;
}

/**
 * This class will unify HTTP Services.
 */
export class HTTPService {
  /**
   * Endpoint URI.
   */
  private readonly endpoint: string = "/api.php";

  /**
   * This method will make connection to the backend.
   * @param params The parameters
   * @returns Promise
   */
  private async api<T = void>(params?: { [key: string]: any }): Promise<any> {
    if (params !== undefined) {
      params["csrf_token"] = localStorage.getItem("csrfToken");
      params["session_id"] = getAttributeFromSession("sessionId");
      params["session_token"] = getAttributeFromSession("sessionToken");
      params["app_version"] = APP_VERSION;
    }
    return await axiosInstance.post<APIResponse<T>>(this.endpoint, params);
  }

  /**
   * This method will fetch the data from the API.
   * @param params parameters
   * @param action
   * @returns Promise
   */
  public async fetch<T>(
    params: { [key: string]: any },
    action: string
  ): Promise<any> {
    params["action"] = action;
    return await this.api<T>(params);
  }

  /**
   * This method will add the data object.
   * @param values New Data Object
   * @param action
   * @returns Promise
   */
  public async add<T = void>(
    values: { [key: string]: any },
    action: string
  ): Promise<any> {
    let payload = JSON.parse(JSON.stringify(values));
    payload["action"] = action;
    return await this.api<T>(payload);
  }

  /**
   * This method will update the data object.
   * @param values Updated Data Object
   * @param action
   * @returns Promise
   */
  public async update<T = void>(
    values: { [key: string]: any },
    action: string
  ): Promise<any> {
    let payload = JSON.parse(JSON.stringify(values));
    payload["action"] = action;
    return await this.api<T>(payload);
  }
}
